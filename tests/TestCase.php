<?php

declare(strict_types=1);

namespace BBSLab\NovaPermission\Tests;

use BBSLab\NovaPermission\NovaPermissionServiceProvider;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Foundation\Application;
use Laravel\Nova\Nova;
use Laravel\Nova\NovaCoreServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;
use Spatie\Permission\PermissionRegistrar;
use Spatie\Permission\PermissionServiceProvider;
use Workbench\App\Models\User;
use Workbench\App\Nova\Post as PostResource;
use Workbench\App\Nova\Service as ServiceResource;
use Workbench\App\Nova\User as UserResource;

abstract class TestCase extends Orchestra
{
    /**
     * @param  Application  $app
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            NovaCoreServiceProvider::class,
            PermissionServiceProvider::class,
            NovaPermissionServiceProvider::class,
        ];
    }

    /**
     * @param  Application  $app
     */
    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.key', 'base64:'.base64_encode(str_repeat('a', 32)));
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
        $app['config']->set('auth.providers.users.model', User::class);
        $app['config']->set('permission.cache.store', 'array');
        // Keep the suite hermetic: the package cache uses the default store, and
        // a built workbench can leave `database` synced into the testbench config.
        $app['config']->set('cache.default', 'array');
        // Same reason — pin the package config to its defaults so a synced
        // workbench config (which enables demo authorizable models) can't leak in.
        $app['config']->set('nova-permission.authorizable_models', []);
        $app['config']->set('nova-permission.cache.enabled', false);
    }

    protected function setUp(): void
    {
        parent::setUp();

        Factory::guessFactoryNamesUsing(static fn (string $model): string => 'Workbench\\Database\\Factories\\'.class_basename($model).'Factory');

        $this->migrateSchema();

        // Register the workbench Nova resources so the policy can resolve
        // permission names from each resource's $permissionsForAbilities.
        Nova::resources([PostResource::class, ServiceResource::class, UserResource::class]);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    protected function migrateSchema(): void
    {
        $root = dirname(__DIR__);

        $migrations = array_merge([
            $root.'/vendor/orchestra/testbench-core/laravel/migrations/0001_01_01_000000_testbench_create_users_table.php',
            $root.'/vendor/spatie/laravel-permission/database/migrations/create_permission_tables.php.stub',
            $root.'/database/migrations/add_authorizable_and_group_to_permissions_table.php',
            $root.'/database/migrations/add_override_permission_to_roles_table.php',
        ], glob($root.'/workbench/database/migrations/*.php') ?: []);

        foreach ($migrations as $file) {
            (include $file)->up();
        }
    }
}
