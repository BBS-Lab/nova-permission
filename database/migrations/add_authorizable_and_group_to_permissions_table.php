<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tableName = config('permission.table_names.permissions');

        Schema::table($tableName, function (Blueprint $table) {
            $table->unsignedBigInteger('authorizable_id')->nullable()->after('guard_name');
            $table->string('authorizable_type')->nullable()->after('authorizable_id');
            $table->string('group')->nullable()->after('authorizable_type');
        });

        // A permission name may now exist once as a general (unscoped) permission
        // AND once per authorizable instance, so uniqueness must include the
        // authorizable morph rather than being (name, guard_name) alone.
        Schema::table($tableName, function (Blueprint $table) {
            $table->dropUnique(['name', 'guard_name']);
            // Explicit index name: the auto-generated one
            // (permissions_name_guard_name_authorizable_id_authorizable_type_unique)
            // is 68 chars and exceeds MySQL/MariaDB's 64-char identifier limit.
            $table->unique(
                ['name', 'guard_name', 'authorizable_id', 'authorizable_type'],
                'nova_permission_authorizable_unique',
            );
        });
    }

    public function down(): void
    {
        $tableName = config('permission.table_names.permissions');

        Schema::table($tableName, function (Blueprint $table) {
            $table->dropUnique('nova_permission_authorizable_unique');
            $table->unique(['name', 'guard_name']);
        });

        Schema::table($tableName, function (Blueprint $table) {
            $table->dropColumn(['authorizable_id', 'authorizable_type', 'group']);
        });
    }
};
