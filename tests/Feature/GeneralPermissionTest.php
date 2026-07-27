<?php

declare(strict_types=1);

use BBSLab\NovaPermission\Console\Commands\GenerateResourcePermissions;
use BBSLab\NovaPermission\Models\Role;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Gate;
use Workbench\App\Models\Post;
use Workbench\App\Models\User;

/**
 * "General" (type-level) permissions on the Post resource: each Nova ability is
 * gated by a `{ability} post` permission, granted either through a role or as a
 * direct permission. Post carries no instance-scoped permissions here, so the
 * per-model abilities fall back to the general permission.
 */
beforeEach(function (): void {
    Artisan::call(GenerateResourcePermissions::class);
});

dataset('post abilities', [
    'viewAny' => ['viewAny', false],
    'create' => ['create', false],
    'view' => ['view', true],
    'update' => ['update', true],
    'replicate' => ['replicate', true],
    'delete' => ['delete', true],
    'restore' => ['restore', true],
    'forceDelete' => ['forceDelete', true],
]);

function postTarget(bool $needsModel): string|Post
{
    return $needsModel ? Post::factory()->create() : Post::class;
}

it('allows an ability granted through a role', function (string $ability, bool $needsModel): void {
    $user = User::factory()->create();
    $role = Role::query()->create(['name' => 'editor', 'guard_name' => 'web']);
    $role->givePermissionTo("{$ability} post");
    $user->assignRole($role);

    expect(Gate::forUser($user)->check($ability, postTarget($needsModel)))->toBeTrue();
})->with('post abilities');

it('allows an ability granted as a direct permission', function (string $ability, bool $needsModel): void {
    $user = User::factory()->create();
    $user->givePermissionTo("{$ability} post");

    expect(Gate::forUser($user)->check($ability, postTarget($needsModel)))->toBeTrue();
})->with('post abilities');

it('denies an ability that was never granted', function (string $ability, bool $needsModel): void {
    $user = User::factory()->create();

    expect(Gate::forUser($user)->check($ability, postTarget($needsModel)))->toBeFalse();
})->with('post abilities');

it('denies an ability the user holds only for another resource', function (string $ability, bool $needsModel): void {
    // Holding "{ability} service" must not grant "{ability} post".
    $user = User::factory()->create();
    $user->givePermissionTo("{$ability} service");

    expect(Gate::forUser($user)->check($ability, postTarget($needsModel)))->toBeFalse();
})->with('post abilities');
