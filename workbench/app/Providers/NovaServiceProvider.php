<?php

declare(strict_types=1);

namespace Workbench\App\Providers;

use BBSLab\NovaPermission\PermissionBuilder;
use Illuminate\Support\Facades\Gate;
use Laravel\Nova\Dashboards\Main;
use Laravel\Nova\Nova;
use Laravel\Nova\NovaApplicationServiceProvider;
use Workbench\App\Nova\Post;
use Workbench\App\Nova\Service;
use Workbench\App\Nova\User;

class NovaServiceProvider extends NovaApplicationServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        parent::boot();
        $this->resources();
    }

    /**
     * Register the Nova routes.
     */
    protected function routes(): void
    {
        // Nova::routes() registers the full Nova UI (dashboard, resources, login)
        // — DevTool::routes() only wires the devtool/auth routes, which left
        // /nova returning 404. Kept to the API shared by Nova 4 and 5.
        $registration = Nova::routes()
            ->withAuthenticationRoutes(default: true)
            ->withPasswordResetRoutes();

        if (method_exists($registration, 'withoutEmailVerificationRoutes')) {
            $registration->withoutEmailVerificationRoutes();
        }

        $registration->register();
    }

    /**
     * Register the Nova gate.
     *
     * This gate determines who can access Nova in non-local environments.
     */
    protected function gate(): void
    {
        Gate::define('viewNova', function ($user) {
            return true;
        });
    }

    /**
     * Get the dashboards that should be listed in the Nova sidebar.
     */
    protected function dashboards(): array
    {
        return [
            new Main,
        ];
    }

    /**
     * Get the tools that should be listed in the Nova sidebar.
     */
    public function tools(): array
    {
        return [
            PermissionBuilder::make()->canSee(function ($request) {
                return $request->user()->hasRole('admin');
            }),
        ];
    }

    /**
     * Register the application's Nova resources.
     *
     * Registered explicitly (rather than via laravel/nova-devtool's
     * DevTool::resourcesIn) so the package has no Nova-5-only dev dependency and
     * the workbench resolves on both Nova 4 and Nova 5.
     */
    protected function resources(): void
    {
        Nova::resources([
            Post::class,
            Service::class,
            User::class,
        ]);
    }
}
