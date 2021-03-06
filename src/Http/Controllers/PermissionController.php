<?php

namespace BBSLab\NovaPermission\Http\Controllers;

use BBSLab\NovaPermission\Actions\GenerateResourcePermissionsAction;
use BBSLab\NovaPermission\Http\Requests\AttachRequest;
use BBSLab\NovaPermission\Http\Requests\PermissionByAuthorizableRequest;
use BBSLab\NovaPermission\Http\Requests\PermissionByGroupRequest;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Laravel\Nova\Nova;
use Spatie\Permission\PermissionRegistrar;

class PermissionController
{
    /** @var \BBSLab\NovaPermission\Contracts\Role */
    protected $roleModel;

    /** @var \BBSLab\NovaPermission\Contracts\Permission */
    protected $permissionModel;

    /**
     * Create new PermissionController instance.
     *
     * @param  \Spatie\Permission\PermissionRegistrar  $registrar
     */
    public function __construct(PermissionRegistrar $registrar)
    {
        $this->roleModel = $registrar->getRoleClass();
        $this->permissionModel = $registrar->getPermissionClass();
    }

    protected function getRoles(): Collection
    {
        return $this->roleModel->newQuery()
            ->select('id', 'name', 'guard_name')
            ->where('override_permission', '=', false)
            ->orderBy('name')
            ->get();
    }

    /**
     * @param  string $search
     * @return \Illuminate\Support\Collection
     */
    protected function getSimpleGroups($search): Collection
    {
        return $this->permissionModel->newQuery()
            ->select('group', 'authorizable_id', 'authorizable_type', 'guard_name')
            ->distinct()
            ->whereNull(['authorizable_id', 'authorizable_id'])
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
                        $permission->display = $resource::label();
                    }
                }

                return $permission;
            });
    }

    /**
     * @param  string $search
     * @return \Illuminate\Support\Collection
     */
    protected function getModelGroups($search): Collection
    {
        return $this->permissionModel->newQuery()
            ->select('authorizable_id', 'authorizable_type', 'guard_name')
            ->distinct()
            ->with('authorizable')
            ->whereNotNull(['authorizable_id', 'authorizable_id'])
            ->when(! empty($search), function ($query) use ($search) {
                return $query->where('name', 'like', "%$search%");
            })
            ->get()
            ->map(function ($permission) {
                /** @var \Laravel\Nova\Resource $resource */
                $resource = Nova::newResourceFromModel($permission->authorizable);

                $permission->display = $resource::singularLabel().': '.$resource->title();

                unset($permission->authorizable);

                return $permission;
            })
            ->filter();
    }

    /**
     * Get the permission groups.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function groups(Request $request)
    {
        $search = trim($request->query('search'));

        return response()->json([
            'roles' => $this->getRoles(),
            'groups' => $this->getSimpleGroups($search)
                ->concat($this->getModelGroups($search))
                ->sortBy('group')
                ->values()
                ->all(),
        ]);
    }

    /**
     * @param  string  $guard
     * @param  string  $search
     * @return \Illuminate\Database\Eloquent\Builder
     */
    protected function getPermissionQuery(string $guard, string $search): EloquentBuilder
    {
        return $this->permissionModel->newQuery()
            ->select('id', 'name', 'guard_name')
            ->with('roles')
            ->where('guard_name', '=', $guard)
            ->when(! empty($search), function (EloquentBuilder $query) use ($search) {
                return $query->where('name', 'like', "%$search%");
            })
            ->orderBy('name');
    }

    /**
     * Get permissions by group name.
     *
     * @param  \BBSLab\NovaPermission\Http\Requests\PermissionByGroupRequest  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function permissionsByGroup(PermissionByGroupRequest $request)
    {
        $roles = $this->getRoles();

        $permissions = $this->getPermissionQuery($request->guard, $request->searchValue())
            ->when(empty($request->group), function (EloquentBuilder $query) {
                return $query->whereNull(['group', 'authorizable_id', 'authorizable_type']);
            })
            ->when(! empty($request->group), function (EloquentBuilder $query) use ($request) {
                return $query->where('group', '=', $request->group)
                    ->whereNull(['authorizable_id', 'authorizable_type']);
            })
            ->get()
            ->map
            ->serializeForPermissionBuilder($roles);

        return response()->json($permissions);
    }

    /**
     * Get permissions by authorizable information.
     *
     * @param  \BBSLab\NovaPermission\Http\Requests\PermissionByAuthorizableRequest  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function permissionsByAuthorizable(PermissionByAuthorizableRequest $request)
    {
        $roles = $this->getRoles();

        $permissions = $this->getPermissionQuery($request->guard, $request->searchValue())
            ->where([
                'authorizable_id' => $request->id,
                'authorizable_type' => $request->type,
            ])
            ->get()
            ->map
            ->serializeForPermissionBuilder($roles);

        return response()->json($permissions);
    }

    /**
     * Attach or detach permissions.
     *
     * @param  \BBSLab\NovaPermission\Http\Requests\AttachRequest  $request
     * @param $role
     * @return \Illuminate\Http\JsonResponse
     */
    public function attachPermission(AttachRequest $request, $role)
    {
        /** @var \BBSLab\NovaPermission\Models\Role $role */
        $role = $this->roleModel->newQuery()->findOrFail($role);

        $method = $request->attach ? 'syncWithoutDetaching' : 'detach';
        $message = $request->attach ? 'nova-permission::permission-builder.attached' : 'nova-permission::permission-builder.detached';

        $role->permissions()->{$method}($request->permissions);

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return response()->json([
            'message' => trans_choice($message, count($request->permissions)),
        ]);
    }

    /**
     * Generate resources permissions.
     *
     * @param  \BBSLab\NovaPermission\Actions\GenerateResourcePermissionsAction  $generateResourcePermissionsAction
     * @return \Illuminate\Http\JsonResponse
     */
    public function generatePermission(GenerateResourcePermissionsAction $generateResourcePermissionsAction)
    {
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
