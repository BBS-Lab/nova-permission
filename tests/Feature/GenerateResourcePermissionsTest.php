<?php

declare(strict_types=1);

use BBSLab\NovaPermission\Console\Commands\GenerateResourcePermissions;
use BBSLab\NovaPermission\Models\Permission;
use Illuminate\Support\Facades\Artisan;
use Workbench\App\Nova\Service as ServiceResource;

it('generates the general permissions for every ability of a resource', function (): void {
    Artisan::call(GenerateResourcePermissions::class);

    foreach (['viewAny', 'view', 'create', 'update', 'replicate', 'delete', 'restore', 'forceDelete'] as $ability) {
        $permission = Permission::query()
            ->where('name', '=', "{$ability} post")
            ->whereNull('authorizable_id')
            ->first();

        expect($permission)->not->toBeNull()
            ->and($permission->group)->toBe('Post')
            ->and($permission->guard_name)->toBe('web');
    }
});

it('generates permissions for each resource that declares abilities', function (): void {
    Artisan::call(GenerateResourcePermissions::class);

    expect(Permission::query()->where('name', 'view post')->exists())->toBeTrue()
        ->and(Permission::query()->where('name', 'view service')->exists())->toBeTrue()
        ->and(Permission::query()->where('name', 'view user')->exists())->toBeTrue()
        ->and(Permission::query()->where('name', 'view permission')->exists())->toBeTrue()
        ->and(Permission::query()->where('name', 'view role')->exists())->toBeTrue();
});

it('is idempotent', function (): void {
    Artisan::call(GenerateResourcePermissions::class);
    $count = Permission::query()->count();

    Artisan::call(GenerateResourcePermissions::class);

    expect(Permission::query()->count())->toBe($count);
});

it('skips resources listed in generate_without_resources', function (): void {
    config(['nova-permission.generate_without_resources' => [ServiceResource::class]]);

    Artisan::call(GenerateResourcePermissions::class);

    expect(Permission::query()->where('name', 'view service')->exists())->toBeFalse()
        ->and(Permission::query()->where('name', 'view post')->exists())->toBeTrue();
});

it('reports success from the console command', function (): void {
    $exit = Artisan::call(GenerateResourcePermissions::class);

    expect($exit)->toBe(0)
        ->and(Artisan::output())->toContain('Permission generated');
});
