<?php

declare(strict_types=1);

namespace Workbench\App\Policies;

use BBSLab\NovaPermission\Policies\Policy;
use Workbench\App\Models\User;

class UserPolicy extends Policy
{
    protected function model(): string
    {
        return User::class;
    }
}
