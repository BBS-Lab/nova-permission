<?php

namespace BBSLab\NovaPermission\Traits;

use Laravel\Nova\Authorizable as BaseTrait;

trait Authorizable
{
    use BaseTrait;

    public static function hasAbilities(): bool
    {
        return isset(static::$permissionsForAbilities) && ! empty(static::$permissionsForAbilities);
    }
}
