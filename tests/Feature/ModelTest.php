<?php

declare(strict_types=1);

use BBSLab\NovaPermission\Console\Commands\GenerateResourcePermissions;
use BBSLab\NovaPermission\Models\Permission;
use BBSLab\NovaPermission\Models\Role;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\PermissionRegistrar;
use Workbench\App\Models\Service;
use Workbench\App\Models\User;

function modelScopedPermission(Service $service): Permission
{
    return Permission::query()->create([
        'name' => 'view service',
        'guard_name' => 'web',
        'authorizable_type' => $service->getMorphClass(),
        'authorizable_id' => $service->getKey(),
    ]);
}

it('exposes the authorizable morph relation', function (): void {
    $service = Service::factory()->create();
    $permission = modelScopedPermission($service);

    expect($permission->authorizable->is($service))->toBeTrue();
});

it('flushes the authorization cache when a scoped permission is deleted', function (): void {
    config(['nova-permission.cache.enabled' => true]);

    $service = Service::factory()->create();
    $user = User::factory()->create();
    $permission = modelScopedPermission($service);
    $user->givePermissionTo($permission);

    // Warm the per-model cache with the scoped grant (instance-override -> true).
    expect($user->hasPermissionToOnModel('view service', $service))->toBeTrue();

    // Deleting the scoped permission fires the `deleting` model event, which
    // flushes the cached lookup; the next check must fall back (no general grant).
    $permission->delete();
    app()->make(PermissionRegistrar::class)->forgetCachedPermissions();

    expect($user->hasPermissionToOnModel('view service', $service))->toBeFalse();
});

it('serializes for the permission builder with per-role toggles', function (): void {
    $permission = Permission::query()->create(['name' => 'view post', 'guard_name' => 'web']);
    $granted = Role::query()->create(['name' => 'granted', 'guard_name' => 'web']);
    $other = Role::query()->create(['name' => 'other', 'guard_name' => 'web']);
    $granted->givePermissionTo($permission);
    $permission->load('roles');

    $data = $permission->serializeForPermissionBuilder(collect([$granted, $other]));

    expect($data['id'])->toBe($permission->getKey())
        ->and($data['name'])->toBe('view post')
        ->and($data['guard_name'])->toBe('web')
        ->and($data['roles'][$granted->getKey()])->toBeTrue()
        ->and($data['roles'][$other->getKey()])->toBeFalse();
});

it('serializes against all roles when none are supplied', function (): void {
    $permission = Permission::query()->create(['name' => 'view post', 'guard_name' => 'web']);
    Role::query()->create(['name' => 'only', 'guard_name' => 'web']);

    $data = $permission->serializeForPermissionBuilder();

    expect($data['roles'])->toHaveCount(1);
});

it('invalidates the per-model authorization cache when a scoped permission is created', function (): void {
    config(['nova-permission.cache.enabled' => true]);

    Artisan::call(GenerateResourcePermissions::class);

    $service = Service::factory()->create();
    $user = User::factory()->create();
    $user->givePermissionTo(
        Permission::query()->whereNull('authorizable_id')->where('name', 'view service')->firstOrFail()
    );

    // Warms the cache: no scoped permission yet, so access is granted via the general one.
    expect(Gate::forUser($user)->check('view', $service))->toBeTrue();

    // Creating a scoped permission fires forgetPermissionFromCache; the next check
    // must see it (instance-override) instead of the stale cached lookup.
    modelScopedPermission($service);

    expect(Gate::forUser($user)->check('view', $service))->toBeFalse();
});
