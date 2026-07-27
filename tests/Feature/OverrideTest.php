<?php

declare(strict_types=1);

use BBSLab\NovaPermission\Console\Commands\GenerateResourcePermissions;
use BBSLab\NovaPermission\Models\Role;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Gate;
use Workbench\App\Models\Post;
use Workbench\App\Models\User;

beforeEach(function (): void {
    Artisan::call(GenerateResourcePermissions::class);
});

function overrideUser(): User
{
    $role = Role::query()->create(['name' => 'super', 'guard_name' => 'web', 'override_permission' => true]);
    $user = User::factory()->create();
    $user->assignRole($role);

    return $user;
}

it('reports canOverridePermission for a user holding an override role', function (): void {
    expect(overrideUser()->canOverridePermission())->toBeTrue();
});

it('reports no override for a plain role or no role', function (): void {
    $role = Role::query()->create(['name' => 'plain', 'guard_name' => 'web']);
    $withRole = User::factory()->create();
    $withRole->assignRole($role);

    expect($withRole->canOverridePermission())->toBeFalse()
        ->and(User::factory()->create()->canOverridePermission())->toBeFalse();
});

it('grants every ability to an override user, even without explicit permissions', function (): void {
    $user = overrideUser();
    $post = Post::factory()->create();

    expect(Gate::forUser($user)->check('viewAny', Post::class))->toBeTrue()
        ->and(Gate::forUser($user)->check('create', Post::class))->toBeTrue()
        ->and(Gate::forUser($user)->check('view', $post))->toBeTrue()
        ->and(Gate::forUser($user)->check('update', $post))->toBeTrue()
        ->and(Gate::forUser($user)->check('delete', $post))->toBeTrue()
        ->and(Gate::forUser($user)->check('forceDelete', $post))->toBeTrue();
});

it('does not grant abilities to a non-override user without permissions', function (): void {
    $post = Post::factory()->create();
    $user = User::factory()->create();

    expect(Gate::forUser($user)->check('viewAny', Post::class))->toBeFalse()
        ->and(Gate::forUser($user)->check('view', $post))->toBeFalse();
});

it('recomputes the override after forgetting its cache', function (): void {
    $role = Role::query()->create(['name' => 'super', 'guard_name' => 'web', 'override_permission' => true]);
    $user = User::factory()->create();
    $user->assignRole($role);

    expect($user->canOverridePermission())->toBeTrue();

    $role->update(['override_permission' => false]);
    $user->forgetOverridePermission();

    expect($user->canOverridePermission())->toBeFalse();
});
