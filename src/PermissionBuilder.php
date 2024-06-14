<?php

declare(strict_types=1);

namespace BBSLab\NovaPermission;

use Illuminate\Http\Request;
use Laravel\Nova\Menu\MenuSection;
use Laravel\Nova\Nova;
use Laravel\Nova\Tool;

class PermissionBuilder extends Tool
{
    /**
     * Perform any tasks that need to happen when the tool is booted.
     *
     * @return void
     */
    public function boot()
    {
        Nova::script('nova-permission', __DIR__.'/../dist/js/tool.js');
        Nova::style('nova-permission', __DIR__.'/../dist/css/tool.css');

        $this->loadNovaTranslations();
    }

    protected function loadNovaTranslations()
    {
        $translations = collect(trans('nova-permission::permission-builder'))->mapWithKeys(function ($value, $key) {
            return ["permission-builder::{$key}" => $value];
        })->toArray();

        Nova::translations($translations);

        Nova::provideToScript([
            'translations' => Nova::allTranslations(),
        ]);
    }

    public function menu(Request $request)
    {
        return MenuSection::make('Permission Builder')
            ->path('nova-permission')
            ->icon('server');
    }
}
