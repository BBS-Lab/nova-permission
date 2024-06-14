<?php

declare(strict_types=1);

namespace BBSLab\NovaPermission\Contracts;

interface HasAbilities
{
    public static function hasAbilities(): bool;
}
