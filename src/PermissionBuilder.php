<?php

namespace BBSLab\NovaPermission;

use BBSLab\NovaPermission\Resources\Permission;
use BBSLab\NovaPermission\Resources\Role;
use Laravel\Nova\Nova;
use Laravel\Nova\Tool;
use Spatie\Permission\PermissionRegistrar;

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

        $this->registerResources(app(PermissionRegistrar::class));
        $this->loadNovaTranslations();
    }

    protected function registerResources(PermissionRegistrar $registrar)
    {
        Permission::$model = get_class($registrar->getPermissionClass());
        Role::$model = get_class($registrar->getRoleClass());

        Nova::resources([
            Permission::class,
            Role::class,
        ]);
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

    /**
     * Build the view that renders the navigation links for the tool.
     *
     * @return \Illuminate\View\View
     */
    public function renderNavigation()
    {
        return view('nova-permission::navigation');
    }
}
