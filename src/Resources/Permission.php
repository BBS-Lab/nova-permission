<?php

namespace BBSLab\NovaPermission\Resources;

use BBSLab\NovaPermission\Contracts\HasAbilities;
use BBSLab\NovaPermission\Traits\Authorizable;
use BBSLab\NovaPermission\Traits\HasFieldName;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Laravel\Nova\Fields\BelongsToMany;
use Laravel\Nova\Fields\DateTime;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\MorphTo;
use Laravel\Nova\Fields\Select;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Resource;

class Permission extends Resource implements HasAbilities
{
    use Authorizable,
        HasFieldName;

    public static $permissionsForAbilities = [
        'viewAny' => 'viewAny permission',
        'view' => 'view permission',
        'create' => 'create permission',
        'update' => 'update permission',
        'delete' => 'delete permission',
    ];

    /**
     * The model the resource corresponds to.
     *
     * @var string
     */
    public static $model;

    /**
     * The single value that should be used to represent the resource when being displayed.
     *
     * @var string
     */
    public static $title = 'name';

    /**
     * Get the search result subtitle for the resource.
     *
     * @return string|null
     */
    public function subtitle()
    {
        return "Guard: {$this->guard_name}";
    }

    /**
     * The columns that should be searched.
     *
     * @var array
     */
    public static $search = [
        'name',
        'guard_name',
    ];

    /**
     * Get the displayable label of the resource.
     *
     * @return string
     */
    public static function label()
    {
        return trans('nova-permission::resources.permission.label');
    }

    /**
     * Get the displayable singular label of the resource.
     *
     * @return string
     */
    public static function singularLabel()
    {
        return trans('nova-permission::resources.permission.singular_label');
    }

    /**
     * Get the fields displayed by the resource.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function fields(Request $request)
    {
        $guardOptions = collect(config('auth.guards'))->mapWithKeys(function ($value, $key) {
            return [$key => $key];
        });

        $fields = [
            ID::make()->sortable(),
            Text::make($this->getTranslatedFieldName('Name'), 'name')
                ->rules('required', 'string', 'max:255'),
            Text::make($this->getTranslatedFieldName('Group'), 'group')
                ->rules('nullable', 'string', 'max:255')
                ->help(trans('nova-permission::resources.permission.group_help')),
            Select::make($this->getTranslatedFieldName('Guard name'), 'guard_name')
                ->options($guardOptions->toArray())
                ->rules(['required', Rule::in($guardOptions)]),
        ];

        $models = config('nova-permission.authorizable_models', []);

        if (! empty($models)) {
            $fields[] = MorphTo::make($this->getTranslatedFieldName('Authorizable model'), 'authorizable')
                ->types($models)
                ->searchable()
                ->nullable();
        }

        return array_merge($fields, [
            DateTime::make($this->getTranslatedFieldName('Created at'), 'created_at')
                ->onlyOnDetail(),
            DateTime::make($this->getTranslatedFieldName('Updated at'), 'updated_at')
                ->onlyOnDetail(),
            BelongsToMany::make(Role::label(), 'roles', Role::class)
                ->searchable()
                ->singularLabel(Role::singularLabel()),
        ]);
    }
}
