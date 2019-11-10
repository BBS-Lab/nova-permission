<?php

namespace BBSLab\NovaPermission\Contracts;

interface CanOverridePermission
{
    /**
     * Determine the user can override permission.
     *
     * @return bool
     */
    public function canOverridePermission(): bool;
}
