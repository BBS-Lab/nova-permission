<?php

namespace BBSLab\NovaPermission\Models;

use BBSLab\NovaPermission\Contracts\Role as RoleContract;
use Spatie\Permission\Models\Role as Model;

/**
 * Class Role.
 *
 * @property int $id
 * @property string $name
 * @property string $guard_name
 * @property bool $override_permission
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class Role extends Model implements RoleContract
{
}
