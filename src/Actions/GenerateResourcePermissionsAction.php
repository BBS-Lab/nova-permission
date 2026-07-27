<?php

declare(strict_types=1);

namespace BBSLab\NovaPermission\Actions;

use BBSLab\NovaPermission\Contracts\HasAbilities;
use BBSLab\NovaPermission\Contracts\Permission;
use Illuminate\Database\Eloquent\Model;
use Laravel\Nova\Nova;
use Laravel\Nova\Resource;
use Spatie\Permission\PermissionRegistrar;

class GenerateResourcePermissionsAction
{
    public function execute(): void
    {
        $guard = config('nova.guard') ?? config('auth.defaults.guard');

        /** @var Permission $permissionModel */
        $permissionModel = app(PermissionRegistrar::class)->getPermissionClass();

        collect(Nova::$resources)->filter(function ($resource) {
            return $this->resourceIsNotExcluded($resource) && $this->resourceHasAbilities($resource);
        })->each(function ($resource) use ($guard, $permissionModel) {
            $group = class_basename($resource);

            foreach ($resource::$permissionsForAbilities as $ability => $permission) {
                // Match on the unique key (name + guard + unscoped morph); `group`
                // is a value so regenerating after a resource rename updates the
                // group instead of colliding with the existing row.
                $permissionModel::query()->updateOrCreate(
                    [
                        'name' => $permission,
                        'guard_name' => $guard,
                        'authorizable_id' => null,
                        'authorizable_type' => null,
                    ],
                    ['group' => $group],
                );
            }
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    protected function resourceIsNotExcluded(string $resource): bool
    {
        return ! in_array($resource, config('nova-permission.generate_without_resources', []));
    }

    /**
     * @param  class-string<\Laravel\Nova\Resource<Model>>  $resource
     */
    protected function resourceHasAbilities(string $resource): bool
    {
        $resource = Nova::resourceInstanceForKey($resource::uriKey());

        if ($resource instanceof HasAbilities) {
            return $resource::hasAbilities();
        }

        return false;
    }
}
