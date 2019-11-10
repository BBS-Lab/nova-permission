<?php

namespace BBSLab\NovaPermission\Traits;

use Spatie\Permission\Traits\HasRoles as BaseTrait;

trait HasRoles
{
    use BaseTrait;

    public function canOverridePermission(): bool
    {
        return $this->roles()
            ->where('override_permission', '=', true)
            ->exists();
    }
}
