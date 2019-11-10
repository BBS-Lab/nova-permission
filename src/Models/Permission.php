<?php

namespace BBSLab\NovaPermission\Models;

use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Collection;
use BBSLab\NovaPermission\Contracts\Permission as PermissionContract;
use Spatie\Permission\Models\Permission as Model;
use Spatie\Permission\PermissionRegistrar;

/**
 * Class Permission
 *
 * @package BBSLab\NovaPermission\Models
 * @property integer $id
 * @property string $name
 * @property string $guard_name
 * @property string $group
 * @property integer $authorizable_id
 * @property string $authorizable_type
 * @property \Illuminate\Database\Eloquent\Collection $roles
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class Permission extends Model implements PermissionContract
{
    /**
     * Get the authorizable instance.
     *
     * @return \Illuminate\Database\Eloquent\Relations\MorphTo
     */
    public function authorizable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Get permission builder representation.
     *
     * @param  \Illuminate\Support\Collection  $roles
     * @return array
     */
    public function serializeForPermissionBuilder(Collection $roles = null): array
    {
        if (empty($roles)) {
            $roles = app(PermissionRegistrar::class)->getRoleClass()
                ->newQuery()
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
                    return [$role->id => true];
                })->toArray()
            ),
        ];
    }
}
