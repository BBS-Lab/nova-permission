<?php

declare(strict_types=1);

namespace BBSLab\NovaPermission\Contracts;

use Illuminate\Database\Eloquent\Model;
use Spatie\Permission\Contracts\Role as Contract;

/**
 * @mixin Model
 */
interface Role extends Contract {}
