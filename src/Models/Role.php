<?php

declare(strict_types=1);

namespace BBSLab\NovaPermission\Models;

use BBSLab\NovaPermission\Contracts\Role as RoleContract;
use Carbon\Carbon;
use Spatie\Permission\Models\Role as Model;

/**
 * Class Role.
 *
 * @property int $id
 * @property string $name
 * @property string $guard_name
 * @property bool $override_permission
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class Role extends Model implements RoleContract {}
