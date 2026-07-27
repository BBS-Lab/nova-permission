<?php

declare(strict_types=1);

namespace BBSLab\NovaPermission\Models;

use BBSLab\NovaPermission\Contracts\Permission as PermissionContract;
use BBSLab\NovaPermission\Support\PermissionCache;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission as Model;
use Spatie\Permission\PermissionRegistrar;

/**
 * Class Permission.
 *
 * @property int $id
 * @property string $name
 * @property string $guard_name
 * @property string $group
 * @property int $authorizable_id
 * @property string $authorizable_type
 * @property \Illuminate\Database\Eloquent\Model|null $authorizable
 * @property \Illuminate\Database\Eloquent\Collection<int, \Illuminate\Database\Eloquent\Model> $roles
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class Permission extends Model implements PermissionContract
{
    protected static function boot(): void
    {
        parent::boot();

        static::saving(function (self $permission) {
            $permission->forgetPermissionFromCache();
        });

        static::deleting(function (self $permission) {
            $permission->forgetPermissionFromCache();
        });
    }

    /**
     * @return MorphTo<\Illuminate\Database\Eloquent\Model, \Illuminate\Database\Eloquent\Model>
     */
    public function authorizable(): MorphTo
    {
        /** @var MorphTo<\Illuminate\Database\Eloquent\Model, \Illuminate\Database\Eloquent\Model> $relation */
        $relation = $this->morphTo();

        return $relation;
    }

    /**
     * @param  Collection<int, Role>|null  $roles
     * @return array<string, mixed>
     */
    public function serializeForPermissionBuilder(?Collection $roles = null): array
    {
        if (empty($roles)) {
            $roleClass = app(PermissionRegistrar::class)->getRoleClass();

            $roles = $roleClass::query()
                ->orderBy('name')
                ->get();
        }

        $roles = array_fill_keys($roles->pluck('id')->toArray(), false);

        return [
            'id' => $this->id,
            'name' => $this->name,
            'guard_name' => $this->guard_name,
            'roles' => array_replace(
                $roles,
                $this->roles->mapWithKeys(function ($role) {
                    return [$role->getKey() => true];
                })->toArray()
            ),
        ];
    }

    public function forgetPermissionFromCache(): void
    {
        if ($this->authorizable) {
            $key = implode(':', [
                'nova-permission',
                'authorization',
                $this->getOriginal('authorizable_type', $this->authorizable_type),
                $this->getOriginal('authorizable_id', $this->authorizable_id),
                Str::snake($this->getOriginal('name', $this->name)),
            ]);

            PermissionCache::forget($key);
        }
    }
}
