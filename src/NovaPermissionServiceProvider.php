<?php

namespace BBSLab\NovaPermission;

use BBSLab\NovaPermission\Console\Commands\GenerateResourcePermissions;
use BBSLab\NovaPermission\Contracts\CanOverridePermission;
use BBSLab\NovaPermission\Http\Middleware\Authorize;
use BBSLab\NovaPermission\Resources\Permission;
use BBSLab\NovaPermission\Resources\Role;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Laravel\Nova\Nova;
use Spatie\Permission\PermissionRegistrar;

class NovaPermissionServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     *
     * @param  \Illuminate\Filesystem\Filesystem  $filesystem
     * @return void
     */
    public function boot(Filesystem $filesystem)
    {
        $this->publishes([
            __DIR__.'/../config/permission.php' => config_path('permission.php'),
            __DIR__.'/../config/nova-permission.php' => config_path('nova-permission.php'),
        ], 'config');

        $this->publishes([
            __DIR__.'/../resources/lang' => resource_path('lang/vendor/nova-permission'),
        ], 'translations');

        $this->publishMigrations($filesystem);

        $this->loadViewsFrom(__DIR__.'/../resources/views', 'nova-permission');
        $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'nova-permission');

        $this->app->booted(function () {
            $this->routes();
        });

        if ($this->app->runningInConsole()) {
            $this->commands([
                GenerateResourcePermissions::class,
            ]);
        }

        Gate::after(function ($user, $ability) {
            if ($user instanceof CanOverridePermission) {
                return $user->canOverridePermission();
            }

            return false;
        });

        $this->registerResources(app(PermissionRegistrar::class));
    }

    protected function registerResources(PermissionRegistrar $registrar)
    {
        Model::unguard(true);
        Permission::$model = get_class($registrar->getPermissionClass());
        Role::$model = get_class($registrar->getRoleClass());
        Model::unguard(false);

        Nova::resources([
            Permission::class,
            Role::class,
        ]);
    }

    protected function publishMigrations(Filesystem $filesystem): void
    {
        $migrations = collect([
            'add_authorizable_and_group_to_permissions_table.php',
            'add_override_permission_to_roles_table.php',
        ])->mapWithKeys(function ($file) use ($filesystem) {
            $key = __DIR__."/../database/migrations/{$file}.stub";

            return [$key => $this->getMigrationFileName($filesystem, $file)];
        })->toArray();

        $this->publishes($migrations, 'migrations');
    }

    /**
     * Register the tool's routes.
     *
     * @return void
     */
    protected function routes()
    {
        if ($this->app->routesAreCached()) {
            return;
        }

        Route::middleware(['nova', Authorize::class])
            ->prefix('nova-vendor/nova-permission')
            ->group(__DIR__.'/../routes/api.php');
    }

    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        $this->mergeConfigFrom(__DIR__.'/../config/permission.php', 'permission');
        $this->mergeConfigFrom(__DIR__.'/../config/nova-permission.php', 'nova-permission');
    }

    /**
     * Returns existing migration file if found, else uses the current timestamp.
     *
     * @param  \Illuminate\Filesystem\Filesystem  $filesystem
     * @param  string  $file
     * @return string
     */
    protected function getMigrationFileName(Filesystem $filesystem, string $file): string
    {
        $timestamp = date('Y_m_d_His');

        return Collection::make($this->app->databasePath().DIRECTORY_SEPARATOR.'migrations'.DIRECTORY_SEPARATOR)
            ->flatMap(function ($path) use ($filesystem, $file) {
                return $filesystem->glob("{$path}*_{$file}");
            })->push("{$this->app->databasePath()}/migrations/{$timestamp}_{$file}")
            ->first();
    }
}
