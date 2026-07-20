<?php

use Modules\Acl\Models\Permission;
use Modules\Acl\Models\Role;
use Modules\Acl\Models\User;

test('guests cannot access permissions list', function () {
    $this->getJson('/api/v1/permissions')->assertStatus(401);
});

test('authorized user can list permissions', function () {
    $user = User::factory()->create();
    Permission::create(['name' => 'view-dashboard', 'display_name' => ['en' => 'View Dashboard']]);

    $this->actingAs($user, 'sanctum')
        ->getJson('/api/v1/permissions?with_roles=true')
        ->assertStatus(200)
        ->assertJsonStructure([
            'success',
            'message',
            'data' => [
                '*' => ['id', 'name', 'display_name', 'is_active', 'roles'],
            ],
        ]);
});

test('authorized user can create permission', function () {
    $user = User::factory()->create();
    $payload = [
        'name' => 'publish-articles',
        'menu' => 'blog',
    ];
    $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/permissions', $payload)
        ->assertStatus(201)
        ->assertJsonPath('data.name', 'publish-articles')
        ->assertJsonPath('data.menu', 'blog');
});

test('authorized user can see permission details', function () {
    $user = User::factory()->create();
    $permission = Permission::create(['name' => 'delete-users', 'display_name' => ['en' => 'Delete Users']]);

    $this->actingAs($user, 'sanctum')
        ->getJson("/api/v1/permissions/{$permission->id}")
        ->assertStatus(200)
        ->assertJsonPath('data.name', 'delete-users');
});

test('authorized user can update permission', function () {
    $user = User::factory()->create();
    $permission = Permission::create(['name' => 'edit-settings', 'display_name' => ['en' => 'Edit Settings']]);
    $payload = [
        'name' => 'manage-settings',
        'menu' => 'settings',
    ];

    $this->actingAs($user, 'sanctum')
        ->putJson("/api/v1/permissions/{$permission->id}", $payload)
        ->assertStatus(200)
        ->assertJsonPath('data.name', 'manage-settings')
        ->assertJsonPath('data.menu', 'settings');
});

test('authorized user can toggle permission status', function () {
    $user = User::factory()->create();
    $permission = Permission::create(['name' => 'export-data', 'display_name' => ['en' => 'Export Data'], 'is_active' => true]);

    $this->actingAs($user, 'sanctum')
        ->patchJson("/api/v1/permissions/{$permission->id}/toggle-status")
        ->assertStatus(200)
        ->assertJsonPath('data.is_active', false);
});

test('authorized user can soft delete, restore, and force delete a permission', function () {
    $user = User::factory()->create();
    $permission = Permission::create(['name' => 'import-data', 'display_name' => ['en' => 'Import Data']]);

    // Soft Delete
    $this->actingAs($user, 'sanctum')
        ->deleteJson("/api/v1/permissions/{$permission->id}")
        ->assertStatus(200);

    $this->assertSoftDeleted('permissions', ['id' => $permission->id]);

    // Restore
    $this->actingAs($user, 'sanctum')
        ->patchJson("/api/v1/permissions/{$permission->id}/restore")
        ->assertStatus(200)
        ->assertJsonPath('data.deleted_at', null);

    $this->assertDatabaseHas('permissions', ['id' => $permission->id, 'deleted_at' => null]);

    // Soft delete again to force delete
    $permission->delete();

    // Force Delete
    $this->actingAs($user, 'sanctum')
        ->deleteJson("/api/v1/permissions/{$permission->id}/force-delete")
        ->assertStatus(200);

    $this->assertDatabaseMissing('permissions', ['id' => $permission->id]);
});

test('authorized user can perform bulk operations on permissions', function () {
    $user = User::factory()->create();
    $permission1 = Permission::create(['name' => 'perm1', 'display_name' => ['en' => 'Perm 1']]);
    $permission2 = Permission::create(['name' => 'perm2', 'display_name' => ['en' => 'Perm 2']]);
    $ids = [$permission1->id, $permission2->id];

    // Bulk Delete
    $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/permissions/bulk/delete', ['ids' => $ids])
        ->assertStatus(200);

    foreach ($ids as $id) {
        $this->assertSoftDeleted('permissions', ['id' => $id]);
    }

    // Bulk Restore
    $this->actingAs($user, 'sanctum')
        ->patchJson('/api/v1/permissions/bulk/restore', ['ids' => $ids])
        ->assertStatus(200);

    foreach ($ids as $id) {
        $this->assertDatabaseHas('permissions', ['id' => $id, 'deleted_at' => null]);
    }

    // Soft delete again
    Permission::whereIn('id', $ids)->delete();

    // Bulk Force Delete
    $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/permissions/bulk/force-delete', ['ids' => $ids])
        ->assertStatus(200);

    foreach ($ids as $id) {
        $this->assertDatabaseMissing('permissions', ['id' => $id]);
    }
});

test('authorized user can perform bulk toggle status on permissions', function () {
    $user = User::factory()->create();
    $permission1 = Permission::create(['name' => 'perm-a', 'display_name' => ['en' => 'Perm A'], 'is_active' => true]);
    $permission2 = Permission::create(['name' => 'perm-b', 'display_name' => ['en' => 'Perm B'], 'is_active' => true]);
    $ids = [$permission1->id, $permission2->id];

    $this->actingAs($user, 'sanctum')
        ->patchJson('/api/v1/permissions/bulk/toggle-status', ['ids' => $ids])
        ->assertStatus(200);

    foreach ($ids as $id) {
        $this->assertDatabaseHas('permissions', ['id' => $id, 'is_active' => false]);
    }
});

test('authorized user can manage permission roles', function () {
    $user = User::factory()->create();
    $permission = Permission::create(['name' => 'access-all', 'display_name' => ['en' => 'Access All']]);

    $role1 = Role::create(['name' => 'admin-role', 'display_name' => ['en' => 'Admin Role']]);
    $role2 = Role::create(['name' => 'staff-role', 'display_name' => ['en' => 'Staff Role']]);

    // Assign Roles
    $this->actingAs($user, 'sanctum')
        ->postJson("/api/v1/permissions/{$permission->id}/assign-roles", ['roles' => ['admin-role', 'staff-role']])
        ->assertStatus(200);

    $this->assertTrue($permission->fresh()->roles->contains('name', 'admin-role'));
    $this->assertTrue($permission->fresh()->roles->contains('name', 'staff-role'));

    // Remove Roles
    $this->actingAs($user, 'sanctum')
        ->postJson("/api/v1/permissions/{$permission->id}/remove-roles", ['roles' => ['admin-role']])
        ->assertStatus(200);

    $this->assertFalse($permission->fresh()->roles->contains('name', 'admin-role'));
    $this->assertTrue($permission->fresh()->roles->contains('name', 'staff-role'));

    // Sync Roles
    $this->actingAs($user, 'sanctum')
        ->postJson("/api/v1/permissions/{$permission->id}/sync-roles", ['roles' => ['admin-role']])
        ->assertStatus(200);

    $this->assertTrue($permission->fresh()->roles->contains('name', 'admin-role'));
    $this->assertFalse($permission->fresh()->roles->contains('name', 'staff-role'));
});
