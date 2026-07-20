<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $tableNames = config('permission.table_names');

        if (empty($tableNames)) {
            return;
        }

        // Add display_name, is_active, and softDeletes to roles
        Schema::table($tableNames['roles'], function (Blueprint $table) {
            $table->json('display_name')->nullable()->after('name');
            $table->boolean('is_active')->default(true)->after('display_name');
            $table->softDeletes()->after('is_active');
        });

        // Add display_name, menu, is_active, and softDeletes to permissions
        Schema::table($tableNames['permissions'], function (Blueprint $table) {
            $table->json('display_name')->nullable()->after('name');
            $table->string('menu')->nullable()->after('display_name');
            $table->boolean('is_active')->default(true)->after('menu');
            $table->softDeletes()->after('is_active');
        });

        // Migrate existing names to display_names
        $roles = DB::table($tableNames['roles'])->get();
        foreach ($roles as $role) {
            DB::table($tableNames['roles'])->where('id', $role->id)->update([
                'display_name' => json_encode(['en' => ucwords(str_replace(['-', '_'], ' ', $role->name))]),
            ]);
        }

        $permissions = DB::table($tableNames['permissions'])->get();
        foreach ($permissions as $permission) {
            DB::table($tableNames['permissions'])->where('id', $permission->id)->update([
                'display_name' => json_encode(['en' => ucwords(str_replace(['-', '_'], ' ', $permission->name))]),
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $tableNames = config('permission.table_names');

        if (empty($tableNames)) {
            return;
        }

        Schema::table($tableNames['roles'], function (Blueprint $table) {
            $table->dropColumn(['display_name', 'is_active', 'deleted_at']);
        });

        Schema::table($tableNames['permissions'], function (Blueprint $table) {
            $table->dropColumn(['display_name', 'menu', 'is_active', 'deleted_at']);
        });
    }
};
