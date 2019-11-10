<?php

namespace BBSLab\NovaPermission\Traits;

use BBSLab\NovaPermission\Contracts\HasAuthorizations;
use Spatie\Permission\Traits\HasRoles as BaseTrait;

trait HasRoles
{
    use BaseTrait;

    public function canOverridePermission(): bool
    {
        $guard = config('nova.guard') ?? config('auth.defaults.guard');

        return $this->roles()
            ->where('guard_name', '=', $guard)
            ->where('override_permission', '=', true)
            ->exists();
    }

    public function hasPermissionToOnModel($permission, $model = null, $guardName = null): bool
    {
        if (empty($model) || ! $model instanceof HasAuthorizations) {
            return $this->hasPermissionTo($permission, $guardName);
        }

        $authorization = $model->authorizations()
            ->where('name', '=', $permission)
            ->when(! empty($guardName), function ($query) use ($guardName) {
                $query->where('guard_name', '=', $guardName);
            })
            ->first();

        return ! empty($authorization)
            ? $this->hasPermissionTo($authorization)
            : $this->hasPermissionTo($permission, $guardName);
    }
}
