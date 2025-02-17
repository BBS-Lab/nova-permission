<?php

declare(strict_types=1);

namespace BBSLab\NovaPermission\Traits;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Laravel\Nova\Authorizable as BaseTrait;

trait Authorizable
{
    use BaseTrait;

    public static function hasAbilities(): bool
    {
        return isset(static::$permissionsForAbilities) && !empty(static::$permissionsForAbilities);
    }

    public static function cacheTtl()
    {
        return config('nova-permission.gate_cache');
    }

    public static function cacheKey(string $action, $request, $resource = null)
    {
        return implode(':', array_filter([
            'administrator',
            optional($request->user())->getKey() ?? 'unauthenticated',
            'can',
            $action,
            static::$model,
            optional($resource)->getKey(),
        ]));
    }

    /**
     * Determine if the resource should be available for the given request.
     *
     * @return bool
     */
    public static function authorizedToViewAny(Request $request)
    {
        $key = static::cacheKey('viewAny', $request);

        return Cache::remember($key, static::cacheTtl(), function () {
            if (!static::authorizable()) {
                return true;
            }

            return method_exists(Gate::getPolicyFor(static::newModel()), 'viewAny')
                ? Gate::check('viewAny', get_class(static::newModel()))
                : true;
        });
    }

    /**
     * Determine if the current user can create new resources.
     *
     * @return bool
     */
    public static function authorizedToCreate(Request $request)
    {
        $key = static::cacheKey('create', $request);

        return Cache::remember($key, static::cacheTtl(), function () {
            if (static::authorizable()) {
                return Gate::check('create', get_class(static::newModel()));
            }

            return true;
        });
    }

    /**
     * Determine if the current user can view the given resource.
     *
     * @param  string  $ability
     * @return bool
     */
    public function authorizedTo(Request $request, $ability)
    {
        $key = static::cacheKey($ability, $request, $this->resource);

        return Cache::remember($key, static::cacheTtl(), function () use ($ability) {
            return static::authorizable() ? Gate::check($ability, $this->resource) : true;
        });
    }
}
