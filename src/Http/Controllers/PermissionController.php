<?php

declare(strict_types=1);

namespace BBSLab\NovaPermission\Http\Controllers;

use BBSLab\NovaPermission\Actions\GenerateResourcePermissionsAction;
use BBSLab\NovaPermission\Contracts\Permission;
use BBSLab\NovaPermission\Http\Requests\AttachRequest;
use BBSLab\NovaPermission\Http\Requests\PermissionByAuthorizableRequest;
use BBSLab\NovaPermission\Http\Requests\PermissionByGroupRequest;
use BBSLab\NovaPermission\Models\Role;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Laravel\Nova\Nova;
use Laravel\Nova\Resource;
use Spatie\Permission\PermissionRegistrar;

class PermissionController
{
    /** @var class-string<Role> */
    protected $roleModel;

    /** @var class-string<\BBSLab\NovaPermission\Models\Permission> */
    protected $permissionModel;

    /**
     * Create new PermissionController instance.
     */
    public function __construct(PermissionRegistrar $registrar)
    {
        /** @var class-string<Role> $roleModel */
        $roleModel = $registrar->getRoleClass();
        $this->roleModel = $roleModel;

        /** @var class-string<\BBSLab\NovaPermission\Models\Permission> $permissionModel */
        $permissionModel = $registrar->getPermissionClass();
        $this->permissionModel = $permissionModel;
    }

    /**
     * @return Collection<int, Role>
     */
    protected function getRoles(): Collection
    {
        return $this->roleModel::query()
            ->select('id', 'name', 'guard_name')
            ->where('override_permission', '=', false)
            ->orderBy('name')
            ->get();
    }

    /**
     * @return Collection<int, Model>
     */
    protected function getSimpleGroups(string $search): Collection
    {
        return $this->permissionModel::query()
            ->select('group', 'authorizable_id', 'authorizable_type', 'guard_name')
            ->distinct()
            ->whereNull(['authorizable_id', 'authorizable_type'])
            ->when(! empty($search), function ($query) use ($search) {
                return $query->where(function ($query) use ($search) {
                    $query->where('group', 'like', "%$search%")
                        ->orWhere('name', 'like', "%$search%");
                });
            })
            ->orderBy('group')
            ->get()
            ->map(function ($permission) {
                if (! empty($permission->group)) {
                    $key = Str::plural(Str::kebab($permission->group));
                    $resource = Nova::resourceForKey($key);

                    if (! empty($resource)) {
                        // @phpstan-ignore property.notFound (transient view attribute serialized to the builder)
                        $permission->display = $resource::label();
                    }
                }

                return $permission;
            });
    }

    /**
     * @return Collection<int, Model>
     */
    protected function getModelGroups(string $search): Collection
    {
        return $this->permissionModel::query()
            ->select('authorizable_id', 'authorizable_type', 'guard_name')
            ->distinct()
            ->with('authorizable')
            ->whereNotNull(['authorizable_id', 'authorizable_type'])
            ->when(! empty($search), function ($query) use ($search) {
                return $query->where('name', 'like', "%$search%");
            })
            ->get()
            ->map(function ($permission) {
                $authorizable = $permission->authorizable;

                // A scoped permission is orphaned if its authorizable row was
                // deleted without cleanup; skip it so ->filter() drops it rather
                // than throwing when resolving a Nova resource from null.
                if ($authorizable === null) {
                    return null;
                }

                /** @var \Laravel\Nova\Resource<Model> $resource */
                $resource = Nova::newResourceFromModel($authorizable);

                // @phpstan-ignore property.notFound (transient view attribute serialized to the builder)
                $permission->display = $resource::singularLabel().': '.$resource->title();

                unset($permission->authorizable);

                return $permission;
            })
            ->filter();
    }

    public function groups(Request $request): JsonResponse
    {
        $search = trim($request->query('search', ''));

        return response()->json([
            'roles' => $this->getRoles(),
            'groups' => $this->getSimpleGroups($search)
                ->concat($this->getModelGroups($search)->toArray())
                ->sortBy('group')
                ->values()
                ->all(),
        ]);
    }

    /**
     * @return EloquentBuilder<\BBSLab\NovaPermission\Models\Permission>
     */
    protected function getPermissionQuery(string $guard, string $search): EloquentBuilder
    {
        return $this->permissionModel::query()
            ->select('id', 'name', 'guard_name')
            ->with('roles')
            ->where('guard_name', '=', $guard)
            ->when(! empty($search), function (EloquentBuilder $query) use ($search) {
                return $query->where('name', 'like', "%$search%");
            })
            ->orderBy('name');
    }

    public function permissionsByGroup(PermissionByGroupRequest $request): JsonResponse
    {
        $roles = $this->getRoles();

        $permissions = $this->getPermissionQuery($request->guard, $request->searchValue())
            ->whereNull(['authorizable_id', 'authorizable_type'])
            ->when(empty($request->group), function (EloquentBuilder $query) {
                return $query->whereNull('group');
            })
            ->when(! empty($request->group), function (EloquentBuilder $query) use ($request) {
                return $query->where('group', '=', $request->group);
            })
            ->get()
            ->map(fn (Permission $permission) => $permission->serializeForPermissionBuilder($roles));

        return response()->json($permissions);
    }

    public function permissionsByAuthorizable(PermissionByAuthorizableRequest $request): JsonResponse
    {
        $roles = $this->getRoles();

        $permissions = $this->getPermissionQuery($request->guard, $request->searchValue())
            ->where(['authorizable_id' => $request->id, 'authorizable_type' => $request->type])
            ->get()
            ->map(fn (Permission $permission) => $permission->serializeForPermissionBuilder($roles));

        return response()->json($permissions);
    }

    public function attachPermission(AttachRequest $request, int|string $role): JsonResponse
    {
        /** @var Role $role */
        $role = $this->roleModel::query()->findOrFail($role);

        $method = $request->attach ? 'syncWithoutDetaching' : 'detach';
        $message = $request->attach
            ? 'nova-permission::permission-builder.attached'
            : 'nova-permission::permission-builder.detached';

        $role->permissions()->{$method}($request->permissions);

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return response()->json([
            'message' => trans_choice($message, count($request->permissions)),
        ]);
    }

    public function generatePermission(
        GenerateResourcePermissionsAction $generateResourcePermissionsAction
    ): JsonResponse {
        // Nova's resources are already booted here: the route runs under the
        // `nova` middleware group, whose DispatchServingNovaEvent fires
        // ServingNova (which registers the resources) before this controller.
        try {
            $generateResourcePermissionsAction->execute();

            return response()->json([
                'message' => trans('nova-permission::permission-builder.permissions-generated'),
            ]);
        } catch (\Exception $exception) {
            abort(500, $exception->getMessage());
        }
    }
}
