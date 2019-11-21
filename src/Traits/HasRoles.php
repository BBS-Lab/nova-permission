<?php

namespace BBSLab\NovaPermission\Traits;

use BBSLab\NovaPermission\Contracts\HasAuthorizations;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;
use Spatie\Permission\Traits\HasRoles as BaseTrait;

trait HasRoles
{
    use BaseTrait;

    public function getOverridePermissionCacheTags(): array
    {
        return ['nova-permission', 'can-override'];
    }

    public function getOverridePermissionCacheKey(): string
    {
        return implode(':', [
            'nova-permission',
            'can-override',
            'user:'.$this->getKey(),
        ]);
    }

    public function forgetOverridePermission(): void
    {
        /** @var \Illuminate\Contracts\Cache\Factory $cacheManager */
        $cacheManager = app('cache');

        $cacheManager->flush($this->getOverridePermissionCacheKey());
    }

    public function canOverridePermission(): bool
    {
        /** @var \Illuminate\Contracts\Cache\Factory $cacheManager */
        $cacheManager = app('cache');

        $cache = method_exists($cacheManager->store(), 'tags')
            ? $cacheManager->store()->tags($this->getOverridePermissionCacheTags())
            : $cacheManager->store();

        $key = $this->getOverridePermissionCacheKey();

        return $cache->remember($key, PermissionRegistrar::$cacheExpirationTime, function () {
            $guard = config('nova.guard') ?? config('auth.defaults.guard');

            return $this->roles()
                ->where('guard_name', '=', $guard)
                ->where('override_permission', '=', true)
                ->exists();
        });
    }

    public function hasPermissionToOnModel($permission, $model = null, $guardName = null): bool
    {
        if (empty($model) || ! $model instanceof HasAuthorizations) {
            return $this->can($permission);
        }

        $key = implode(':', [
            'nova-permission',
            'authorization',
            class_basename($model),
            $model->getKey(),
            Str::snake($permission),
        ]);

        /** @var \Illuminate\Contracts\Cache\Factory $cacheManager */
        $cacheManager = app('cache');

        $authorization = $cacheManager->store()
            ->remember($key, PermissionRegistrar::$cacheExpirationTime, function () use ($permission, $model, $guardName) {
                return $model->authorizations()
                    ->where('name', '=', $permission)
                    ->where('guard_name', '=', $guardName ?? $this->getDefaultGuardName())
                    ->first();
            });

        return ! empty($authorization)
            ? $this->hasPermissionTo($authorization)
            : $this->can($permission);
    }
}
