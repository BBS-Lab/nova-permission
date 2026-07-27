<?php

declare(strict_types=1);

use BBSLab\NovaPermission\Console\Commands\GenerateResourcePermissions;
use BBSLab\NovaPermission\Models\Permission;
use BBSLab\NovaPermission\Models\Role;
use Illuminate\Support\Facades\Artisan;
use Workbench\App\Models\Service;
use Workbench\App\Models\User;

/**
 * Authorizations::scopeAuthorize must agree, at the query level, with the policy's
 * instance-override semantics for a given permission.
 */
beforeEach(function (): void {
    Artisan::call(GenerateResourcePermissions::class);
});

function scopedViewPermission(Service $service): Permission
{
    return Permission::query()->create([
        'name' => 'view service',
        'guard_name' => 'web',
        'authorizable_type' => $service->getMorphClass(),
        'authorizable_id' => $service->getKey(),
    ]);
}

function generalViewPermission(): Permission
{
    return Permission::query()
        ->whereNull('authorizable_id')
        ->where('name', '=', 'view service')
        ->where('guard_name', '=', 'web')
        ->firstOrFail();
}

/**
 * @return array{Service, Service} [open (unscoped), locked (scoped)]
 */
function twoServices(): array
{
    $open = Service::factory()->create();
    $locked = Service::factory()->create();
    scopedViewPermission($locked);

    return [$open, $locked];
}

function authorizedServiceIds(User $user): array
{
    return Service::query()->authorize($user, 'view service')->pluck('id')->sort()->values()->all();
}

it('returns only unscoped instances to a holder of the general permission', function (): void {
    [$open] = twoServices();

    $user = User::factory()->create();
    $user->givePermissionTo(generalViewPermission());

    expect(authorizedServiceIds($user))->toBe([$open->getKey()]);
});

it('returns a scoped instance to the holder of its instance permission (direct)', function (): void {
    [, $locked] = twoServices();

    $user = User::factory()->create();
    $user->givePermissionTo(Permission::query()->where('authorizable_id', $locked->getKey())->firstOrFail());

    expect(authorizedServiceIds($user))->toBe([$locked->getKey()]);
});

it('returns a scoped instance to the holder of its instance permission (role)', function (): void {
    [, $locked] = twoServices();

    $role = Role::query()->create(['name' => 'service-manager', 'guard_name' => 'web']);
    $role->givePermissionTo(Permission::query()->where('authorizable_id', $locked->getKey())->firstOrFail());

    $user = User::factory()->create();
    $user->assignRole($role);

    expect(authorizedServiceIds($user))->toBe([$locked->getKey()]);
});

it('returns both scoped and unscoped instances with general + instance permissions', function (): void {
    [$open, $locked] = twoServices();

    $user = User::factory()->create();
    $user->givePermissionTo([
        generalViewPermission(),
        Permission::query()->where('authorizable_id', $locked->getKey())->firstOrFail(),
    ]);

    expect(authorizedServiceIds($user))->toBe([$open->getKey(), $locked->getKey()]);
});

it('returns nothing to a user without any matching permission', function (): void {
    twoServices();

    $user = User::factory()->create();

    expect(authorizedServiceIds($user))->toBe([]);
});

it('returns every instance to a user with an override role', function (): void {
    [$open, $locked] = twoServices();

    $role = Role::query()->create(['name' => 'super', 'guard_name' => 'web', 'override_permission' => true]);
    $user = User::factory()->create();
    $user->assignRole($role);

    expect(authorizedServiceIds($user))->toBe([$open->getKey(), $locked->getKey()]);
});
