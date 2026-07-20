<?php

declare(strict_types=1);

namespace Modules\Acl\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Modules\Acl\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // Create permissions
        $permissions = [
            'manage users',
            'manage roles',
            'view products',
            'manage products',
            'view orders',
            'manage orders',
        ];

        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        // Create roles and assign created permissions

        // Admin role gets all permissions
        $adminRole = Role::findOrCreate('admin', 'web');
        $adminRole->givePermissionTo(Permission::all());

        // Standard user role
        $userRole = Role::findOrCreate('user', 'web');
        $userRole->givePermissionTo([
            'view products',
            'view orders',
        ]);

        // Seed default Admin User
        $adminUser = User::updateOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'Admin User',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
                'is_active' => true,
            ]
        );
        $adminUser->assignRole($adminRole);

        // Seed default Regular User
        $regularUser = User::updateOrCreate(
            ['email' => 'user@example.com'],
            [
                'name' => 'Regular User',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
                'is_active' => true,
            ]
        );
        $regularUser->assignRole($userRole);
    }
}
