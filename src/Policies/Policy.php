<?php

declare(strict_types=1);

namespace BBSLab\NovaPermission\Policies;

use BBSLab\NovaPermission\Contracts\HasAbilities;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Laravel\Nova\Nova;

abstract class Policy
{
    use HandlesAuthorization;

    /**
     * @return class-string<Model>
     */
    abstract protected function model(): string;

    public static function guard(): string
    {
        return config('nova.guard') ?? config('auth.defaults.guard');
    }

    /**
     * @param  Model|class-string<Model>|null  $model
     */
    protected function getPermissionFromResource(string $permission, Model|string|null $model = null): ?string
    {
        if (! $resourceClass = Nova::resourceForModel($model ?? $this->model())) {
            return null;
        }

        if (! is_subclass_of($resourceClass, HasAbilities::class)) {
            return null;
        }

        if (! $resourceClass::hasAbilities()) {
            return null;
        }

        return Arr::get($resourceClass::$permissionsForAbilities, $permission);
    }

    /**
     * @param  Model|class-string<Model>|null  $model
     */
    protected function getPermissionName(string $permission, Model|string|null $model = null): string
    {
        return $this->getPermissionFromResource($permission, $model) ?? $permission.' '.Str::snake(
            class_basename($model ?? $this->model()),
            ' '
        );
    }

    protected function can(Authorizable $user, string $permission, ?Model $model = null): bool
    {
        if (empty($model)) {
            return $user->can(
                $this->getPermissionName($permission, $model)
            );
        }

        return $user->hasPermissionToOnModel(
            $this->getPermissionName($permission, $model),
            $model,
            static::guard()
        );
    }

    public function viewAny(Authorizable $user): ?bool
    {
        if ($this->can($user, 'viewAny')) {
            return true;
        }

        return null;
    }

    public function view(Authorizable $user, Model $model): ?bool
    {
        if ($this->can($user, 'view', $model)) {
            return true;
        }

        return null;
    }

    public function create(Authorizable $user): ?bool
    {
        if ($this->can($user, 'create')) {
            return true;
        }

        return null;
    }

    public function update(Authorizable $user, Model $model): ?bool
    {
        if ($this->can($user, 'update', $model)) {
            return true;
        }

        return null;
    }

    public function replicate(Authorizable $user, Model $model): ?bool
    {
        if ($this->can($user, 'replicate', $model)) {
            return true;
        }

        return null;
    }

    public function delete(Authorizable $user, Model $model): ?bool
    {
        if ($this->can($user, 'delete', $model)) {
            return true;
        }

        return null;
    }

    public function restore(Authorizable $user, Model $model): ?bool
    {
        if ($this->can($user, 'restore', $model)) {
            return true;
        }

        return null;
    }

    public function forceDelete(Authorizable $user, Model $model): ?bool
    {
        if ($this->can($user, 'forceDelete', $model)) {
            return true;
        }

        return null;
    }
}
