<?php

declare(strict_types=1);

namespace BBSLab\NovaPermission\Traits;

use BBSLab\NovaPermission\Contracts\HasAuthorizations;
use BBSLab\NovaPermission\Support\PermissionCache;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Spatie\Permission\Contracts\Permission;
use Spatie\Permission\PermissionRegistrar;
use Spatie\Permission\Traits\HasRoles as BaseTrait;

trait HasRoles
{
    use BaseTrait;

    /**
     * Per-request memo for canOverridePermission(). The global Gate::after hook
     * calls it on every gate check (many per Nova page), so we cache the result
     * on the instance to avoid one roles-exists query per check.
     */
    protected ?bool $novaCanOverridePermission = null;

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
        $this->novaCanOverridePermission = null;

        PermissionCache::forget($this->getOverridePermissionCacheKey());
    }

    public function canOverridePermission(): bool
    {
        if ($this->novaCanOverridePermission !== null) {
            return $this->novaCanOverridePermission;
        }

        return $this->novaCanOverridePermission = PermissionCache::remember($this->getOverridePermissionCacheKey(), function () {
            $guard = config('nova.guard') ?? config('auth.defaults.guard');

            return $this->roles()
                ->where('guard_name', '=', $guard)
                ->where('override_permission', '=', true)
                ->exists();
        });
    }

    public function hasPermissionToOnModel(string $permission, ?Model $model = null, ?string $guardName = null): bool
    {
        $guardName = $guardName ?? $this->getDefaultGuardName();

        if (empty($model) || ! $model instanceof HasAuthorizations) {
            return $this->hasGeneralPermissionTo($permission, $guardName);
        }

        // Keep this key in sync with Permission::forgetPermissionFromCache(),
        // which invalidates it using the authorizable_type morph class.
        $key = implode(':', [
            'nova-permission',
            'authorization',
            $model->getMorphClass(),
            $model->getKey(),
            Str::snake($permission),
        ]);

        /** @var Permission|null $authorization */
        $authorization = PermissionCache::remember($key, function () use ($permission, $model, $guardName) {
            return $model->authorizations()
                ->where('name', '=', $permission)
                ->where('guard_name', '=', $guardName)
                ->first();
        });

        // An instance carrying a scoped permission is governed by it alone;
        // otherwise fall back to the general (unscoped) permission.
        return ! empty($authorization)
            ? $this->hasPermissionTo($authorization)
            : $this->hasGeneralPermissionTo($permission, $guardName);
    }

    /**
     * Whether the user holds the general (unscoped) permission of this name.
     *
     * Resolved explicitly on the authorizable-less row so it is never confused
     * with an instance-scoped permission that shares the same name.
     */
    protected function hasGeneralPermissionTo(string $permission, string $guardName): bool
    {
        $permissionClass = app(PermissionRegistrar::class)->getPermissionClass();

        $general = $permissionClass::query()
            ->where('name', '=', $permission)
            ->where('guard_name', '=', $guardName)
            ->whereNull('authorizable_id')
            ->whereNull('authorizable_type')
            ->first();

        return $general !== null && $this->hasPermissionTo($general);
    }
}
