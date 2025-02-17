<?php

declare(strict_types=1);

namespace Workbench\App\Models;

use BBSLab\NovaPermission\Traits\HasRoles;
use Illuminate\Foundation\Auth\User as Authenticable;

class User extends Authenticable
{
    use HasRoles;
}
