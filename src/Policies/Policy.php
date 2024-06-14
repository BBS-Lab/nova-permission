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

    abstract protected function model(): string;

    public static function guard(): string
    {
        return config('nova.guard') ?? config('auth.defaults.guard');
    }

    protected function getPermissionFromResource(string $permission, $model = null): ?string
    {
        if (!$resourceClass = Nova::resourceForModel($model ?? $this->model())) {
            return null;
        }

        if (!is_subclass_of($resourceClass, HasAbilities::class)) {
            return null;
        }

        if (!$resourceClass::hasAbilities()) {
            return null;
        }

        return Arr::get($resourceClass::$permissionsForAbilities, $permission);
    }

    protected function getPermissionName(string $permission, $model = null): string
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

    public function viewAny(Authorizable $user)
    {
        if ($this->can($user, 'viewAny')) {
            return true;
        }
    }

    public function view(Authorizable $user, $model)
    {
        if ($this->can($user, 'view', $model)) {
            return true;
        }
    }

    public function create(Authorizable $user)
    {
        if ($this->can($user, 'create')) {
            return true;
        }
    }

    public function update(Authorizable $user, $model)
    {
        if ($this->can($user, 'update', $model)) {
            return true;
        }
    }

    public function replicate(Authorizable $user, $model)
    {
        if ($this->can($user, 'replicate', $model)) {
            return true;
        }
    }

    public function delete(Authorizable $user, $model)
    {
        if ($this->can($user, 'delete', $model)) {
            return true;
        }
    }

    public function restore(Authorizable $user, $model)
    {
        if ($this->can($user, 'restore', $model)) {
            return true;
        }
    }

    public function forceDelete(Authorizable $user, $model)
    {
        if ($this->can($user, 'forceDelete', $model)) {
            return true;
        }
    }
}
