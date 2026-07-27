<?php

declare(strict_types=1);

use BBSLab\NovaPermission\Support\PermissionCache;

it('runs the callback every time when caching is disabled', function (): void {
    config(['nova-permission.cache.enabled' => false]);

    $calls = 0;
    $callback = function () use (&$calls): string {
        $calls++;

        return 'value';
    };

    expect(PermissionCache::remember('key', $callback))->toBe('value');
    PermissionCache::remember('key', $callback);

    expect($calls)->toBe(2)
        ->and(PermissionCache::enabled())->toBeFalse();
});

it('caches the callback result when caching is enabled', function (): void {
    config(['nova-permission.cache.enabled' => true]);

    $calls = 0;
    $callback = function () use (&$calls): string {
        $calls++;

        return 'value';
    };

    PermissionCache::remember('key', $callback);
    PermissionCache::remember('key', $callback);

    expect($calls)->toBe(1)
        ->and(PermissionCache::enabled())->toBeTrue();
});

it('forgets a cached key', function (): void {
    config(['nova-permission.cache.enabled' => true]);

    PermissionCache::remember('key', fn (): string => 'first');
    PermissionCache::forget('key');

    expect(PermissionCache::remember('key', fn (): string => 'second'))->toBe('second');
});

it('reads the ttl from config', function (): void {
    config(['nova-permission.cache.ttl' => 123]);

    expect(PermissionCache::ttl())->toBe(123);
});
