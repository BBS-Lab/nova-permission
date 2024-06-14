<?php

declare(strict_types=1);

namespace Workbench\App\Nova;

use BBSLab\NovaPermission\Contracts\HasAbilities;
use BBSLab\NovaPermission\Traits\Authorizable;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Fields\Textarea;
use Laravel\Nova\Http\Requests\NovaRequest;

class Post extends Resource implements HasAbilities
{
    use Authorizable;

    public static $permissionsForAbilities = [
        'viewAny' => 'viewAny post',
        'view' => 'view post',
        'create' => 'create post',
        'update' => 'update post',
        'replicate' => 'replicate post',
        'delete' => 'delete post',
        'restore' => 'restore post',
        'forceDelete' => 'forceDelete post',
    ];

    public static $model = \Workbench\App\Models\Post::class;

    public static $title = 'title';

    public static $search = [
        'id', 'title',
    ];

    public function fields(NovaRequest $request): array
    {
        return [
            ID::make()->sortable(),

            Text::make('Title')
                ->sortable()
                ->rules('required', 'max:255'),

            Textarea::make('Content')
                ->sortable()
                ->rules('required'),
        ];
    }
}
