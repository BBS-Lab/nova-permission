<?php

declare(strict_types=1);

namespace Workbench\App\Nova;

use BBSLab\NovaPermission\Contracts\HasAbilities;
use BBSLab\NovaPermission\Resources\Permission;
use BBSLab\NovaPermission\Resources\Role;
use BBSLab\NovaPermission\Traits\Authorizable;
use Illuminate\Validation\Rules;
use Laravel\Nova\Fields\BelongsToMany;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\MorphToMany;
use Laravel\Nova\Fields\Password;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Panel;

class User extends Resource implements HasAbilities
{
    use Authorizable;

    public static $permissionsForAbilities = [
        'viewAny' => 'viewAny user',
        'view' => 'view user',
        'create' => 'create user',
        'update' => 'update user',
        'replicate' => 'replicate user',
        'delete' => 'delete user',
        'restore' => 'restore user',
        'forceDelete' => 'forceDelete user',
    ];

    public static $model = \Workbench\App\Models\User::class;

    public static $title = 'name';

    public static $search = [
        'id', 'name', 'email',
    ];

    public function fields(NovaRequest $request): array
    {
        return [
            ID::make()->sortable(),

            Text::make('Name')
                ->sortable()
                ->rules('required', 'max:255'),

            Text::make('Email')
                ->sortable()
                ->rules('required', 'email', 'max:254')
                ->creationRules('unique:users,email')
                ->updateRules('unique:users,email,{{resourceId}}'),

            Password::make('Password')
                ->onlyOnForms()
                ->creationRules('required', Rules\Password::defaults())
                ->updateRules('nullable', Rules\Password::defaults()),

            Panel::make('Permissions', [
                MorphToMany::make(Role::label(), 'roles', Role::class),
                BelongsToMany::make(Permission::label(), 'permissions', Permission::class),
            ]),
        ];
    }
}
