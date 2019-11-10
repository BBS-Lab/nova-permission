<?php

namespace BBSLab\NovaPermission\Contracts;

interface HasAbilities
{
    /**
     * Determine the resource has abilities defined in the static::$permissionsForAbilities.
     *
     * @return bool
     */
    public static function hasAbilities(): bool;
}
