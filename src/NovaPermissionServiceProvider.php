<?php

declare(strict_types=1);

namespace BBSLab\NovaPermission;

use BBSLab\NovaPermission\Console\Commands\GenerateResourcePermissions;
use BBSLab\NovaPermission\Contracts\CanOverridePermission;
use BBSLab\NovaPermission\Http\Middleware\Authorize;
use BBSLab\NovaPermission\Policies\PermissionPolicy;
use BBSLab\NovaPermission\Policies\RolePolicy;
use BBSLab\NovaPermission\Resources\Permission;
use BBSLab\NovaPermission\Resources\Role;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Laravel\Nova\Http\Middleware\Authenticate;
use Laravel\Nova\Nova;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Spatie\Permission\PermissionRegistrar;

class NovaPermissionServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('nova-permission')
            ->hasConfigFile(['nova-permission', 'permission'])
            ->hasTranslations()
            ->hasMigrations([
                'add_authorizable_and_group_to_permissions_table',
                'add_override_permission_to_roles_table',
            ])
            ->hasCommand(GenerateResourcePermissions::class);
    }

    public function packageBooted(): void
    {
        $this->app->booted(function () {
            $this->routes();
        });

        Gate::after(function ($user, $ability) {
            if ($user instanceof CanOverridePermission) {
                return $user->canOverridePermission();
            }

            return false;
        });

        $registrar = app(PermissionRegistrar::class);

        $this->registerResources($registrar);

        Gate::policy($registrar->getRoleClass(), RolePolicy::class);
        Gate::policy($registrar->getPermissionClass(), PermissionPolicy::class);
    }

    protected function registerResources(PermissionRegistrar $registrar): void
    {
        Model::unguard(true);
        Permission::$model = $registrar->getPermissionClass();
        Role::$model = $registrar->getRoleClass();
        Model::unguard(false);

        Nova::resources([
            Permission::class,
            Role::class,
        ]);
    }

    protected function routes(): void
    {
        if ($this->app->routesAreCached()) {
            return;
        }

        Route::middleware(['nova', Authorize::class])
            ->prefix('nova-vendor/nova-permission')
            ->group(__DIR__.'/../routes/api.php');

        Nova::router(
            ['nova', Authenticate::class, Authorize::class],
            'nova-permission',
        )->group(__DIR__.'/../routes/inertia.php');
    }
}
