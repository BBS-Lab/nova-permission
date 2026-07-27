<?php

declare(strict_types=1);

namespace BBSLab\NovaPermission\Resources;

use BBSLab\NovaPermission\Contracts\HasAbilities;
use BBSLab\NovaPermission\Traits\Authorizable;
use BBSLab\NovaPermission\Traits\HasFieldName;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;
use Laravel\Nova\Fields\BelongsToMany;
use Laravel\Nova\Fields\DateTime;
use Laravel\Nova\Fields\Field;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\MorphTo;
use Laravel\Nova\Fields\Select;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Resource;

/**
 * @extends \Laravel\Nova\Resource<Model>
 *
 * @property-read string $guard_name
 */
class Permission extends Resource implements HasAbilities
{
    use Authorizable,
        HasFieldName;

    /** @var array<string, string> */
    public static $permissionsForAbilities = [
        'viewAny' => 'viewAny permission',
        'view' => 'view permission',
        'create' => 'create permission',
        'update' => 'update permission',
        'replicate' => 'replicate permission',
        'delete' => 'delete permission',
        'restore' => 'restore permission',
        'forceDelete' => 'forceDelete permission',
    ];

    /** @var class-string<Model>|null */
    public static $model;

    public static $title = 'name';

    public function subtitle(): string
    {
        return "Guard: {$this->guard_name}";
    }

    /**
     * The columns that should be searched.
     *
     * @var array<int, string>
     */
    public static $search = [
        'name',
        'guard_name',
    ];

    public static function label(): string
    {
        return trans('nova-permission::resources.permission.label');
    }

    public static function singularLabel(): string
    {
        return trans('nova-permission::resources.permission.singular_label');
    }

    /**
     * @return array<int, Field>
     */
    public function fields(NovaRequest $request): array
    {
        $guardOptions = collect((array) config('auth.guards'))->mapWithKeys(function ($value, $key) {
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
