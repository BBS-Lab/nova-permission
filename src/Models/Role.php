<?php

namespace BBSLab\NovaPermission\Models;

use BBSLab\NovaPermission\Contracts\Role as RoleContract;
use Spatie\Permission\Models\Role as Model;

/**
 * Class Role
 *
 * @package BBSLab\NovaPermission\Models
 * @property integer $id
 * @property string $name
 * @property string $guard_name
 * @property boolean $override_permission
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class Role extends Model implements RoleContract
{

}
