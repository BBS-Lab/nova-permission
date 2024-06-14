<?php

declare(strict_types=1);

namespace BBSLab\NovaPermission\Policies;

use BBSLab\NovaPermission\Models\Permission;

class PermissionPolicy extends Policy
{
    protected function model(): string
    {
        return Permission::class;
    }
}
