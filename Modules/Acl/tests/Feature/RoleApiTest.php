<?php

use Modules\Acl\Models\Permission;
use Modules\Acl\Models\Role;
use Modules\Acl\Models\User;

test('guests cannot access roles list', function () {
    $this->getJson('/api/v1/roles')->assertStatus(401);
});

test('authorized user can list roles', function () {
    $user = User::factory()->create();
    Role::create(['name' => 'manager', 'display_name' => ['en' => 'Manager']]);

    $this->actingAs($user, 'sanctum')
        ->getJson('/api/v1/roles?with_permissions=true')
        ->assertStatus(200)
        ->assertJsonStructure([
            'success',
            'message',
            'data' => [
                '*' => ['id', 'name', 'display_name', 'is_active', 'permissions'],
            ],
        ]);
});

test('authorized user can create role', function () {
    $user = User::factory()->create();
    $payload = [
        'name' => 'superadmin',
    ];
    $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/roles', $payload)
        ->assertStatus(201)
        ->assertJsonPath('data.name', 'superadmin');
});

test('authorized user can see role details', function () {
    $user = User::factory()->create();
    $role = Role::create(['name' => 'auditor', 'display_name' => ['en' => 'Auditor']]);

    $this->actingAs($user, 'sanctum')
        ->getJson("/api/v1/roles/{$role->id}")
        ->assertStatus(200)
        ->assertJsonPath('data.name', 'auditor');
});

test('authorized user can update role', function () {
    $user = User::factory()->create();
    $role = Role::create(['name' => 'staff', 'display_name' => ['en' => 'Staff']]);
    $payload = [
        'name' => 'senior-staff',
    ];

    $this->actingAs($user, 'sanctum')
        ->putJson("/api/v1/roles/{$role->id}", $payload)
        ->assertStatus(200)
        ->assertJsonPath('data.name', 'senior-staff');
});

test('authorized user can toggle role status', function () {
    $user = User::factory()->create();
    $role = Role::create(['name' => 'officer', 'display_name' => ['en' => 'Officer'], 'is_active' => true]);

    $this->actingAs($user, 'sanctum')
        ->patchJson("/api/v1/roles/{$role->id}/toggle-status")
        ->assertStatus(200)
        ->assertJsonPath('data.is_active', false);
});

test('authorized user can soft delete, restore, and force delete a role', function () {
    $user = User::factory()->create();
    $role = Role::create(['name' => 'guest-role', 'display_name' => ['en' => 'Guest Role']]);

    // Soft Delete
    $this->actingAs($user, 'sanctum')
        ->deleteJson("/api/v1/roles/{$role->id}")
        ->assertStatus(200);

    $this->assertSoftDeleted('roles', ['id' => $role->id]);

    // Restore
    $this->actingAs($user, 'sanctum')
        ->patchJson("/api/v1/roles/{$role->id}/restore")
        ->assertStatus(200)
        ->assertJsonPath('data.deleted_at', null);

    $this->assertDatabaseHas('roles', ['id' => $role->id, 'deleted_at' => null]);

    // Soft delete again to force delete
    $role->delete();

    // Force Delete
    $this->actingAs($user, 'sanctum')
        ->deleteJson("/api/v1/roles/{$role->id}/force-delete")
        ->assertStatus(200);

    $this->assertDatabaseMissing('roles', ['id' => $role->id]);
});

test('authorized user can perform bulk operations on roles', function () {
    $user = User::factory()->create();
    $role1 = Role::create(['name' => 'role1', 'display_name' => ['en' => 'Role 1']]);
    $role2 = Role::create(['name' => 'role2', 'display_name' => ['en' => 'Role 2']]);
    $ids = [$role1->id, $role2->id];

    // Bulk Delete
    $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/roles/bulk/delete', ['ids' => $ids])
        ->assertStatus(200);

    foreach ($ids as $id) {
        $this->assertSoftDeleted('roles', ['id' => $id]);
    }

    // Bulk Restore
    $this->actingAs($user, 'sanctum')
        ->patchJson('/api/v1/roles/bulk/restore', ['ids' => $ids])
        ->assertStatus(200);

    foreach ($ids as $id) {
        $this->assertDatabaseHas('roles', ['id' => $id, 'deleted_at' => null]);
    }

    // Soft delete again
    Role::whereIn('id', $ids)->delete();

    // Bulk Force Delete
    $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/roles/bulk/force-delete', ['ids' => $ids])
        ->assertStatus(200);

    foreach ($ids as $id) {
        $this->assertDatabaseMissing('roles', ['id' => $id]);
    }
});

test('authorized user can perform bulk toggle status on roles', function () {
    $user = User::factory()->create();
    $role1 = Role::create(['name' => 'role-a', 'display_name' => ['en' => 'Role A'], 'is_active' => true]);
    $role2 = Role::create(['name' => 'role-b', 'display_name' => ['en' => 'Role B'], 'is_active' => true]);
    $ids = [$role1->id, $role2->id];

    $this->actingAs($user, 'sanctum')
        ->patchJson('/api/v1/roles/bulk/toggle-status', ['ids' => $ids])
        ->assertStatus(200);

    foreach ($ids as $id) {
        $this->assertDatabaseHas('roles', ['id' => $id, 'is_active' => false]);
    }
});

test('authorized user can manage role permissions', function () {
    $user = User::factory()->create();
    $role = Role::create(['name' => 'moderator', 'display_name' => ['en' => 'Moderator']]);

    $permission1 = Permission::create(['name' => 'edit-posts', 'display_name' => ['en' => 'Edit Posts']]);
    $permission2 = Permission::create(['name' => 'delete-posts', 'display_name' => ['en' => 'Delete Posts']]);

    // Give Permissions
    $this->actingAs($user, 'sanctum')
        ->postJson("/api/v1/roles/{$role->id}/give-permissions", ['permissions' => ['edit-posts', 'delete-posts']])
        ->assertStatus(200);

    $this->assertTrue($role->fresh()->hasAllPermissions(['edit-posts', 'delete-posts']));

    // Revoke Permissions
    $this->actingAs($user, 'sanctum')
        ->postJson("/api/v1/roles/{$role->id}/revoke-permissions", ['permissions' => ['edit-posts']])
        ->assertStatus(200);

    $this->assertFalse($role->fresh()->hasPermissionTo('edit-posts'));
    $this->assertTrue($role->fresh()->hasPermissionTo('delete-posts'));

    // Sync Permissions
    $this->actingAs($user, 'sanctum')
        ->postJson("/api/v1/roles/{$role->id}/sync-permissions", ['permissions' => ['edit-posts']])
        ->assertStatus(200);

    $this->assertTrue($role->fresh()->hasPermissionTo('edit-posts'));
    $this->assertFalse($role->fresh()->hasPermissionTo('delete-posts'));
});
