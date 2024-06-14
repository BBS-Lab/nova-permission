<?php

declare(strict_types=1);

namespace BBSLab\NovaPermission\Resources;

use BBSLab\NovaPermission\Contracts\HasAbilities;
use BBSLab\NovaPermission\Traits\Authorizable;
use BBSLab\NovaPermission\Traits\HasFieldName;
use Illuminate\Validation\Rule;
use Laravel\Nova\Fields\BelongsToMany;
use Laravel\Nova\Fields\Boolean;
use Laravel\Nova\Fields\DateTime;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Select;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Resource;
use Spatie\Permission\PermissionRegistrar;

class Role extends Resource implements HasAbilities
{
    use Authorizable,
        HasFieldName;

    public static $permissionsForAbilities = [
        'viewAny' => 'viewAny role',
        'view' => 'view role',
        'create' => 'create role',
        'update' => 'update role',
        'replicate' => 'replicate role',
        'delete' => 'delete role',
        'restore' => 'restore role',
        'forceDelete' => 'forceDelete role',
    ];

    public static $canSeeOverridePermissionCallback = null;

    public static $model;

//    public static function newModel()
//    {
//        $model =  app(PermissionRegistrar::class)->getRoleClass();
//
//        return new $model;
//    }

    public static function canSeeOverridePermmission($callback)
    {
        static::$canSeeOverridePermissionCallback = $callback;
    }

    public static $title = 'name';

    public function subtitle(): string
    {
        return "Guard: {$this->guard_name}";
    }

    public static $search = [
        'name', 'guard_name',
    ];

    public static function label(): string
    {
        return trans('nova-permission::resources.role.label');
    }

    public static function singularLabel(): string
    {
        return trans('nova-permission::resources.role.singular_label');
    }

    public function fields(NovaRequest $request): array
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
