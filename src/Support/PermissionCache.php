<?php

declare(strict_types=1);

namespace BBSLab\NovaPermission\Support;

use Closure;

/**
 * Thin, config-driven wrapper around the cache used for permission and
 * authorization gate checks. When caching is disabled (the default), callbacks
 * run straight through so checks are always computed fresh.
 */
class PermissionCache
{
    public static function enabled(): bool
    {
        return (bool) config('nova-permission.cache.enabled', false);
    }

    public static function ttl(): int
    {
        return (int) config('nova-permission.cache.ttl', 60 * 60);
    }

    /**
     * @template TValue
     *
     * @param  Closure(): TValue  $callback
     * @return TValue
     */
    public static function remember(string $key, Closure $callback): mixed
    {
        if (! static::enabled()) {
            return $callback();
        }

        return app('cache')->store()->remember($key, static::ttl(), $callback);
    }

    public static function forget(string $key): void
    {
        app('cache')->store()->forget($key);
    }
}
