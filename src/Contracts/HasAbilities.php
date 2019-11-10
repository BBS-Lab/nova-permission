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

    /**
     * Determine the resource has the given ability.
     *
     * @param  string  $ability
     * @return bool
     */
    public static function hasAbility(string $ability): bool;

    /**
     * Determine the model class has a policy method or ability.
     *
     * @param  string  $ability
     * @return bool
     */
    public static function modelHasAbility(string $ability): bool;

    /**
     * Determine the model instance has a policy method or ability.
     *
     * @param  string  $ability
     * @return bool
     */
    public function resourceHasAbility(string $ability): bool;

    /**
     * Determine the current user has the given ability.
     *
     * @param  string  $ability
     * @return mixed
     */
    public static function hasPermissionTo(string $ability);
}
