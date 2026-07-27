<?php

declare(strict_types=1);

use BBSLab\NovaPermission\Models\Permission;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Workbench\App\Models\Post;
use Workbench\App\Models\User;

it('boots with the permission schema in place', function (): void {
    expect(Schema::hasTable('permissions'))->toBeTrue()
        ->and(Schema::hasColumn('permissions', 'authorizable_id'))->toBeTrue()
        ->and(Schema::hasColumn('permissions', 'authorizable_type'))->toBeTrue()
        ->and(Schema::hasColumn('permissions', 'group'))->toBeTrue()
        ->and(Schema::hasColumn('roles', 'override_permission'))->toBeTrue();
});

it('names the composite unique index within MySQL\'s 64-char identifier limit', function (): void {
    // The auto-generated name for a 4-column unique index on `permissions` is 68
    // chars and blows past MySQL/MariaDB's 64-char cap; the migration must pin an
    // explicit short name. SQLite does not enforce the limit, so we assert the
    // named index exists (proving it is not the over-length auto name).
    $indexes = collect(Schema::getIndexes('permissions'))->pluck('name');

    expect($indexes)->toContain('nova_permission_authorizable_unique');

    $indexes->each(fn (string $name) => expect(strlen($name))->toBeLessThanOrEqual(64));
});

it('resolves the PostPolicy through the gate with a general direct permission', function (): void {
    Permission::query()->create(['name' => 'view post', 'guard_name' => 'web']);

    $post = Post::factory()->create();
    $granted = User::factory()->create();
    $granted->givePermissionTo('view post');

    $denied = User::factory()->create();

    expect(Gate::forUser($granted)->check('view', $post))->toBeTrue()
        ->and(Gate::forUser($denied)->check('view', $post))->toBeFalse();
});
