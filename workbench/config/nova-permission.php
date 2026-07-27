<?php

declare(strict_types=1);
use Laravel\Nova\Actions\ActionResource;
use Workbench\App\Nova\Post;
use Workbench\App\Nova\Service;

return [
    'authorizable_models' => [
        Post::class,
        // Service showcases granular (per-instance) permissions; keep it here so
        // the seeded "view service" scoped permission is explorable in the builder.
        Service::class,
    ],

    'generate_without_resources' => [
        ActionResource::class,
    ],

    'cache' => [
        'enabled' => env('NOVA_PERMISSION_CACHE', false),
        'ttl' => env('NOVA_PERMISSION_CACHE_TTL', 60 * 60),
    ],
];
