<?php

declare(strict_types=1);

namespace Workbench\App\Models;

use BBSLab\NovaPermission\Contracts\CanOverridePermission;
use BBSLab\NovaPermission\Traits\HasRoles;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticable;
use Laravel\Nova\Auth\Impersonatable;
use Workbench\Database\Factories\UserFactory;

class User extends Authenticable implements CanOverridePermission
{
    /** @use HasFactory<UserFactory> */
    use HasFactory;

    use HasRoles;

    // Lets you impersonate the seeded users (admin/writer/reader) straight from
    // the Nova user resource to test permissions without logging in and out.
    use Impersonatable;
}
