<?php

declare(strict_types=1);

namespace Workbench\App\Nova;

use BBSLab\NovaPermission\Contracts\HasAbilities;
use BBSLab\NovaPermission\Traits\Authorizable;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Http\Requests\NovaRequest;

class Service extends Resource implements HasAbilities
{
    use Authorizable;

    public static $permissionsForAbilities = [
        'viewAny' => 'viewAny service',
        'view' => 'view service',
        'create' => 'create service',
        'update' => 'update service',
        'replicate' => 'replicate service',
        'delete' => 'delete service',
        'restore' => 'restore service',
        'forceDelete' => 'forceDelete service',
    ];

    public static $model = \Workbench\App\Models\Service::class;

    public static $title = 'name';

    public static $search = [
        'id', 'name',
    ];

    public function fields(NovaRequest $request): array
    {
        return [
            ID::make()->sortable(),

            Text::make('Name')
                ->sortable()
                ->rules('required', 'max:255'),
        ];
    }
}
