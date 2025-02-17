<?php

declare(strict_types=1);

namespace BBSLab\NovaPermission\Contracts;

interface CanOverridePermission
{
    public function canOverridePermission(): bool;
}
