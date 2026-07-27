<?php

declare(strict_types=1);

namespace BBSLab\NovaPermission\Traits;

use BBSLab\NovaPermission\Support\PermissionCache;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Laravel\Nova\Authorizable as BaseTrait;
use Laravel\Nova\Nova;
use Laravel\Nova\Resource;

trait Authorizable
{
    use BaseTrait;

    public static function hasAbilities(): bool
    {
        // isset() guards resources that never declare $permissionsForAbilities;
        // in resources that do, the property is always set (hence the ignore).
        // @phpstan-ignore isset.property
        return isset(static::$permissionsForAbilities) && ! empty(static::$permissionsForAbilities);
    }

    public static function cacheKey(string $action, Request $request, ?Model $resource = null): string
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
     */
    public static function authorizedToViewAny(Request $request): bool
    {
        $key = static::cacheKey('viewAny', $request);

        return PermissionCache::remember($key, function () use ($request) {
            if (! static::authorizable()) {
                return true;
            }

            $resource = static::resolveForAuthorization(static::newResource());

            return ! method_exists(Gate::getPolicyFor($resource), 'viewAny') || Gate::forUser(Nova::user($request))->check('viewAny', $resource::class);
        });
    }

    /**
     * Determine if the current user can create new resources.
     */
    public static function authorizedToCreate(Request $request): bool
    {
        $key = static::cacheKey('create', $request);

        return PermissionCache::remember($key, function () {
            if (static::authorizable()) {
                return Gate::check('create', get_class(static::newModel()));
            }

            return true;
        });
    }

    /**
     * Determine if the current user can view the given resource.
     *
     * $ability is left untyped so the override stays compatible with both
     * Nova 4 (authorizedTo(Request, $ability)) and Nova 5 (…, string $ability): bool).
     *
     * @param  string  $ability
     */
    public function authorizedTo(Request $request, $ability): bool
    {
        $key = static::cacheKey($ability, $request, $this->resource);

        return PermissionCache::remember($key, function () use ($request, $ability) {
            /** @var Model $model */
            $model = $this->resource;

            $resourceClass = Nova::resourceForModel($model);

            if (! $resourceClass) {
                return false;
            }

            $resource = new $resourceClass($model);

            return ! static::authorizable() || Gate::forUser(Nova::user($request))->check($ability, static::resolveForAuthorization($resource));
        });
    }

    /**
     * Resolve the model/resource to authorize against.
     *
     * Mirrors Nova 5's Util::resolveResourceOrModelForAuthorization(), which does
     * not exist in Nova 4; uses only APIs common to both majors so the package
     * works on both.
     *
     * @param  \Laravel\Nova\Resource<Model>  $resource
     * @return Model|\Laravel\Nova\Resource<Model>
     */
    protected static function resolveForAuthorization(Resource $resource)
    {
        // @phpstan-ignore staticProperty.notFound (Nova declares $policy; guarded)
        if (property_exists($resource, 'policy') && ! is_null($resource::$policy)) {
            return $resource;
        }

        return $resource->model() ?? $resource::newModel();
    }
}
