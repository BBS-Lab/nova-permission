<?php

namespace BBSLab\NovaPermission\Actions;

use Laravel\Nova\Nova;
use BBSLab\NovaPermission\Contracts\HasAbilities;
use Spatie\Permission\PermissionRegistrar;

class GenerateResourcePermissionsAction
{
    public function execute()
    {
        $guard = config('nova.guard') ?? config('auth.defaults.guard');

        Nova::resourcesIn(app_path('Nova'));

        /** @var \BBSLab\NovaPermission\Contracts\Permission $permissionModel */
        $permissionModel = app(PermissionRegistrar::class)->getPermissionClass();

        collect(Nova::$resources)->filter(function ($resource) {
            return $this->resourceIsNotExcluded($resource) && $this->resourceHasAbilities($resource);
        })->each(function ($resource) use ($guard, $permissionModel) {
            $group = class_basename($resource);

            foreach ($resource::$permissionsForAbilities as $ability => $permission) {
                $permissionModel->newQuery()->firstOrCreate([
                    'name' => $permission,
                    'group' => $group,
                    'guard_name' => $guard,
                ]);
            }
        });
    }

    protected function resourceIsNotExcluded(string $resource): bool
    {
        return ! in_array($resource, config('nova-permission.generate_without_resources', []));
    }

    protected function resourceHasAbilities(string $resource): bool
    {
        $resource = Nova::resourceInstanceForKey($resource::uriKey());

        if ($resource instanceof HasAbilities) {
            return $resource::hasAbilities();
        }

        return false;
    }
}
