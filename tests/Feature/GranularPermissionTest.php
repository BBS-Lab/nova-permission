<?php

declare(strict_types=1);

use BBSLab\NovaPermission\Console\Commands\GenerateResourcePermissions;
use BBSLab\NovaPermission\Models\Permission;
use BBSLab\NovaPermission\Models\Role;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Gate;
use Workbench\App\Models\Service;
use Workbench\App\Models\User;

/**
 * "Granular" (instance-level) permissions on the Service resource, with the
 * locked "instance-override" semantics:
 *   - an instance WITHOUT a scoped permission falls back to the general permission;
 *   - an instance WITH a scoped permission is reachable only by holders of that
 *     instance permission (through a role or directly) — the general no longer
 *     applies to it.
 */
beforeEach(function (): void {
    // Creates the general (unscoped) "view service" etc.
    Artisan::call(GenerateResourcePermissions::class);
});

function generalViewService(): Permission
{
    return Permission::query()
        ->whereNull('authorizable_id')
        ->where('name', '=', 'view service')
        ->where('guard_name', '=', 'web')
        ->firstOrFail();
}

function scopedViewService(Service $service): Permission
{
    return Permission::query()->create([
        'name' => 'view service',
        'guard_name' => 'web',
        'authorizable_type' => $service->getMorphClass(),
        'authorizable_id' => $service->getKey(),
    ]);
}

it('grants a scoped instance to the holder of its instance permission (direct)', function (): void {
    $locked = Service::factory()->create();
    $open = Service::factory()->create();
    $scoped = scopedViewService($locked);

    $user = User::factory()->create();
    $user->givePermissionTo($scoped);

    expect(Gate::forUser($user)->check('view', $locked))->toBeTrue()
        ->and(Gate::forUser($user)->check('view', $open))->toBeFalse();
});

it('grants a scoped instance to the holder of its instance permission (role)', function (): void {
    $locked = Service::factory()->create();
    $scoped = scopedViewService($locked);

    $role = Role::query()->create(['name' => 'service-manager', 'guard_name' => 'web']);
    $role->givePermissionTo($scoped);

    $user = User::factory()->create();
    $user->assignRole($role);

    expect(Gate::forUser($user)->check('view', $locked))->toBeTrue();
});

it('lets the general permission reach unscoped instances but not scoped ones', function (): void {
    $locked = Service::factory()->create();
    $open = Service::factory()->create();
    scopedViewService($locked); // a scoped permission exists on $locked; the user will NOT hold it

    $user = User::factory()->create();
    $user->givePermissionTo(generalViewService());

    expect(Gate::forUser($user)->check('view', $open))->toBeTrue()      // fallback to general
        ->and(Gate::forUser($user)->check('view', $locked))->toBeFalse(); // instance-override
});

it('grants both when the user holds the general AND the instance permission', function (): void {
    $locked = Service::factory()->create();
    $open = Service::factory()->create();
    $scoped = scopedViewService($locked);

    $user = User::factory()->create();
    $user->givePermissionTo([generalViewService(), $scoped]);

    expect(Gate::forUser($user)->check('view', $locked))->toBeTrue()
        ->and(Gate::forUser($user)->check('view', $open))->toBeTrue();
});

it('grants nothing without any matching permission', function (): void {
    $locked = Service::factory()->create();
    $open = Service::factory()->create();
    scopedViewService($locked);

    $user = User::factory()->create();

    expect(Gate::forUser($user)->check('view', $locked))->toBeFalse()
        ->and(Gate::forUser($user)->check('view', $open))->toBeFalse();
});

it('resolves the general fallback even when the scoped permission was created first', function (): void {
    // Guards against name-ambiguity: scoped row has a LOWER id than the general
    // would if resolution were by name/id order.
    $open = Service::factory()->create();
    $locked = Service::factory()->create();
    scopedViewService($locked); // created before we grant the general below

    $user = User::factory()->create();
    $user->givePermissionTo(generalViewService());

    expect(Gate::forUser($user)->check('view', $open))->toBeTrue();
});
