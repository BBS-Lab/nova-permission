<?php

declare(strict_types=1);

namespace Workbench\Database\Seeders;

use BBSLab\NovaPermission\Console\Commands\GenerateResourcePermissions;
use BBSLab\NovaPermission\Models\Role;
use BBSLab\NovaPermission\Models\Permission;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;
use Workbench\App\Models\Post;
use Workbench\App\Models\User;

class DatabaseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $password = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'; // password

        /** @var \Workbench\App\Models\User $admin */
        $admin = User::query()->create([
            'name' => 'Admin',
            'email' => 'admin@laravel.com',
            'password' => $password,
        ]);

        /** @var \Workbench\App\Models\User $writer */
        $writer = User::query()->create([
            'name' => 'Writer',
            'email' => 'writer@laravel.com',
            'password' => $password,
        ]);

        /** @var \Workbench\App\Models\User $reader */
        $reader = User::query()->create([
            'name' => 'Reader',
            'email' => 'reader@laravel.com',
            'password' => $password,
        ]);

        Artisan::call(GenerateResourcePermissions::class);

        $post = Post::query()->create([
            'title' => 'First post',
            'content' => 'Exercitation occaecat nisi Lorem ipsum non ea proident. Exercitation aute veniam in consequat esse est non mollit. Ipsum anim ad consectetur velit excepteur nulla enim magna ullamco ad consequat culpa. Cupidatat veniam esse commodo irure amet sit cupidatat incididunt mollit est laboris. Ex laborum laboris excepteur aliquip mollit est. Qui Lorem esse tempor laborum aliqua nulla voluptate.',
        ]);

        $customPermission = Permission::query()->create([
            'name' => 'view secret post',
            'guard_name' => 'web',
            'authorizable_type' => $post->getMorphClass(),
            'authorizable_id' => $post->getKey(),
        ]);

        /** @var \BBSLab\NovaPermission\Models\Role $adminRole */
        $adminRole = Role::query()->create([
            'name' => 'admin',
            'guard_name' => 'web',
            'override_permission' => true,
        ]);

        $admin->assignRole('admin');

        $adminRole->givePermissionTo(array_merge(
            array_values(\Workbench\App\Nova\Post::$permissionsForAbilities),
            array_values(\Workbench\App\Nova\User::$permissionsForAbilities),
            array_values(\BBSLab\NovaPermission\Resources\Role::$permissionsForAbilities),
            array_values(\BBSLab\NovaPermission\Resources\Permission::$permissionsForAbilities),
        ));

        /** @var \BBSLab\NovaPermission\Models\Role $writerRole */
        $writerRole = Role::query()->create([
            'name' => 'writer',
            'guard_name' => 'web',
        ]);

        $writer->assignRole('writer');

        $writerRole->givePermissionTo([
            'viewAny post',
            'view post',
            'create post',
            'update post',
            'replicate post',
            'delete post',
            'restore post',
            $customPermission,
        ]);

        /** @var \BBSLab\NovaPermission\Models\Role $readerRole */
        $readerRole = Role::query()->create([
            'name' => 'reader',
            'guard_name' => 'web',
        ]);

        $reader->assignRole('reader');

        $readerRole->givePermissionTo([
            'viewAny post',
            'view post',
        ]);
    }
}
