<?php

declare(strict_types=1);

return [
    'authorizable_models' => [
        // \App\Models\Post::class,
    ],

    'generate_without_resources' => [
        \Laravel\Nova\Actions\ActionResource::class,
    ],

    'gate_cache' => env('NOVA_PERMISSION_GATE_CACHE', 60 * 60),
];
