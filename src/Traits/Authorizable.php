<?php

declare(strict_types=1);

namespace BBSLab\NovaPermission\Traits;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Laravel\Nova\Authorizable as BaseTrait;
use Laravel\Nova\Nova;
use Laravel\Nova\Util;

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

        return Cache::remember($key, static::cacheTtl(), function () use ($request) {
            if (!static::authorizable()) {
                return true;
            }

            $resource = Util::resolveResourceOrModelForAuthorization(static::newResource());

            return !method_exists(Gate::getPolicyFor($resource), 'viewAny') || Gate::forUser(Nova::user($request))->check('viewAny', $resource::class);
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
    public function authorizedTo(Request $request, string $ability): bool
    {
        $key = static::cacheKey($ability, $request, $this->resource);

        return Cache::remember($key, static::cacheTtl(), function () use ($request, $ability) {
            $resource = Util::resolveResourceOrModelForAuthorization($this->resource);

            return !static::authorizable() || Gate::forUser(Nova::user($request))->check($ability, $resource);
        });
    }
}
