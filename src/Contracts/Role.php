<?php

declare(strict_types=1);

namespace BBSLab\NovaPermission\Contracts;

use Spatie\Permission\Contracts\Role as Contract;

/**
 * @mixin \Illuminate\Database\Eloquent\Model
 */
interface Role extends Contract
{
}
