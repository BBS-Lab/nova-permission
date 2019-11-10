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
        $locale = is_readable(__DIR__.'/../resources/lang/'.app()->getLocale().'.json')
            ? app()->getLocale()
            : 'en';

        $file = __DIR__.'/../resources/lang/'.$locale.'.json';

        if (! is_readable($file)) {
            return;
        }

        $translations = json_decode(file_get_contents($file), true);

        $translations = collect($translations)->mapWithKeys(function ($value, $key) {
            return ["permission-builder::{$key}" => $value];
        })->toArray();

        Nova::translations($translations);
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
