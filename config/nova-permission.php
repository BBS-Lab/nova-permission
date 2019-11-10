<?php

return [
    'authorizable_models' => [
        // \App\Models\Post::class,
    ],

    'generate_without_resources' => [
        \Laravel\Nova\Actions\ActionResource::class,
        \BBSLab\NovaPermission\Resources\Role::class,
        \BBSLab\NovaPermission\Resources\Permission::class,
    ]
];
