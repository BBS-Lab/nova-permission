<?php

declare(strict_types=1);

namespace BBSLab\NovaPermission\Policies;

use BBSLab\NovaPermission\Models\Role;

class RolePolicy extends Policy
{
    protected function model(): string
    {
        return Role::class;
    }
}
