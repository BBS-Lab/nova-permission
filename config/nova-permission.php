<?php

declare(strict_types=1);
use Laravel\Nova\Actions\ActionResource;

return [
    'authorizable_models' => [
        // \App\Models\Post::class,
    ],

    'generate_without_resources' => [
        ActionResource::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Gate / authorization cache
    |--------------------------------------------------------------------------
    |
    | Cache the package's permission and authorization gate checks. Disabled by
    | default — enable it only when the read volume justifies it and you accept
    | the staleness window (entries are invalidated on permission save/delete,
    | but not on every conceivable change). "ttl" is the lifetime in seconds.
    |
    */

    'cache' => [
        'enabled' => env('NOVA_PERMISSION_CACHE', false),
        'ttl' => env('NOVA_PERMISSION_CACHE_TTL', 60 * 60),
    ],
];
