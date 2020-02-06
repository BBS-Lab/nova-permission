<?php

namespace BBSLab\NovaPermission\Resources;

use BBSLab\NovaPermission\Contracts\HasAbilities;
use BBSLab\NovaPermission\Traits\Authorizable;
use BBSLab\NovaPermission\Traits\HasFieldName;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Laravel\Nova\Fields\BelongsToMany;
use Laravel\Nova\Fields\Boolean;
use Laravel\Nova\Fields\DateTime;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Select;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Resource;

class Role extends Resource implements HasAbilities
{
    use Authorizable,
        HasFieldName;

    public static $permissionsForAbilities = [
        'viewAny' => 'viewAny role',
        'view' => 'view role',
        'create' => 'create role',
        'update' => 'update role',
        'delete' => 'delete role',
    ];

    public static $canSeeOverridePermissionCallback = null;

    /**
     * The model the resource corresponds to.
     *
     * @var string
     */
    public static $model;

    public static function canSeeOverridePermmission($callback)
    {
        static::$canSeeOverridePermissionCallback = $callback;
    }

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
        'name', 'guard_name',
    ];

    /**
     * Get the displayable label of the resource.
     *
     * @return string
     */
    public static function label()
    {
        return trans('nova-permission::resources.role.label');
    }

    /**
     * Get the displayable singular label of the resource.
     *
     * @return string
     */
    public static function singularLabel()
    {
        return trans('nova-permission::resources.role.singular_label');
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
                ->rules('required', 'string', 'max:255')
                ->creationRules('unique:'.config('permission.table_names.roles'))
                ->updateRules('unique:'.config('permission.table_names.roles').',name,{{resourceId}}'),

            Select::make($this->getTranslatedFieldName('Guard name'), 'guard_name')
                ->options($guardOptions->toArray())
                ->rules(['required', Rule::in($guardOptions)]),
        ];

        $overrideField = Boolean::make($this->getTranslatedFieldName('Override permission'), 'override_permission');

        if (is_callable(static::$canSeeOverridePermissionCallback)) {
            $overrideField->canSee(static::$canSeeOverridePermissionCallback);
        }

        $fields[] = $overrideField;

        return array_merge($fields, [
            DateTime::make($this->getTranslatedFieldName('Created at'), 'created_at')
                ->onlyOnDetail(),
            DateTime::make($this->getTranslatedFieldName('Updated at'), 'updated_at')
                ->onlyOnDetail(),
            BelongsToMany::make(Permission::label(), 'permissions', Permission::class)
                ->searchable()
                ->singularLabel(Permission::singularLabel()),
        ]);
    }
}
