<?php

use Modules\Acl\Models\Role;
use Modules\Acl\Models\User;

test('guests cannot access users list', function () {
    $this->getJson('/api/v1/users')->assertStatus(401);
});

test('authorized user can list users', function () {
    $user = User::factory()->create();
    $this->actingAs($user, 'sanctum')
        ->getJson('/api/v1/users')
        ->assertStatus(200)
        ->assertJsonStructure([
            'success',
            'message',
            'data' => [
                '*' => ['id', 'name', 'email', 'is_active', 'roles', 'permissions'],
            ],
            'links',
            'meta',
        ]);
});

test('authorized user can create user', function () {
    $user = User::factory()->create();
    $payload = [
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ];
    $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/users', $payload)
        ->assertStatus(201)
        ->assertJsonPath('data.name', 'John Doe')
        ->assertJsonPath('data.email', 'john@example.com');
});

test('authorized user can see user details', function () {
    $user = User::factory()->create();
    $target = User::factory()->create();
    $this->actingAs($user, 'sanctum')
        ->getJson("/api/v1/users/{$target->id}")
        ->assertStatus(200)
        ->assertJsonPath('data.id', $target->id);
});

test('authorized user can update user', function () {
    $user = User::factory()->create();
    $target = User::factory()->create();
    $payload = [
        'name' => 'Updated Name',
    ];
    $this->actingAs($user, 'sanctum')
        ->putJson("/api/v1/users/{$target->id}", $payload)
        ->assertStatus(200)
        ->assertJsonPath('data.name', 'Updated Name');
});

test('authorized user can toggle status', function () {
    $user = User::factory()->create(['is_active' => true]);
    $target = User::factory()->create(['is_active' => true]);
    $this->actingAs($user, 'sanctum')
        ->patchJson("/api/v1/users/{$target->id}/toggle-status")
        ->assertStatus(200)
        ->assertJsonPath('data.is_active', false);
});

test('authorized user can soft delete, restore, and force delete a user', function () {
    $user = User::factory()->create();
    $target = User::factory()->create();

    // Soft Delete
    $this->actingAs($user, 'sanctum')
        ->deleteJson("/api/v1/users/{$target->id}")
        ->assertStatus(200);

    $this->assertSoftDeleted('users', ['id' => $target->id]);

    // Restore
    $this->actingAs($user, 'sanctum')
        ->patchJson("/api/v1/users/{$target->id}/restore")
        ->assertStatus(200)
        ->assertJsonPath('data.deleted_at', null);

    $this->assertDatabaseHas('users', ['id' => $target->id, 'deleted_at' => null]);

    // Soft delete again so we can force delete
    $target->delete();

    // Force Delete
    $this->actingAs($user, 'sanctum')
        ->deleteJson("/api/v1/users/{$target->id}/force-delete")
        ->assertStatus(200);

    $this->assertDatabaseMissing('users', ['id' => $target->id]);
});

test('authorized user can perform bulk delete, restore, and force delete', function () {
    $user = User::factory()->create();
    $targets = User::factory()->count(3)->create();
    $ids = $targets->pluck('id')->toArray();

    // Bulk Delete
    $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/users/bulk/delete', ['ids' => $ids])
        ->assertStatus(200)
        ->assertJsonCount(3, 'data.affected');

    foreach ($ids as $id) {
        $this->assertSoftDeleted('users', ['id' => $id]);
    }

    // Bulk Restore
    $this->actingAs($user, 'sanctum')
        ->patchJson('/api/v1/users/bulk/restore', ['ids' => $ids])
        ->assertStatus(200)
        ->assertJsonCount(3, 'data.affected');

    foreach ($ids as $id) {
        $this->assertDatabaseHas('users', ['id' => $id, 'deleted_at' => null]);
    }

    // Soft delete them again so we can bulk force-delete
    User::whereIn('id', $ids)->delete();

    // Bulk Force Delete
    $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/users/bulk/force-delete', ['ids' => $ids])
        ->assertStatus(200)
        ->assertJsonCount(3, 'data.affected');

    foreach ($ids as $id) {
        $this->assertDatabaseMissing('users', ['id' => $id]);
    }
});

test('authorized user can perform bulk toggle status', function () {
    $user = User::factory()->create();
    $targets = User::factory()->count(2)->create(['is_active' => true]);
    $ids = $targets->pluck('id')->toArray();

    $this->actingAs($user, 'sanctum')
        ->patchJson('/api/v1/users/bulk/toggle-status', ['ids' => $ids])
        ->assertStatus(200);

    foreach ($ids as $id) {
        $this->assertDatabaseHas('users', ['id' => $id, 'is_active' => false]);
    }
});

test('authorized user can manage user roles', function () {
    $user = User::factory()->create();
    $target = User::factory()->create();

    $role1 = Role::create(['name' => 'editor', 'display_name' => ['en' => 'Editor']]);
    $role2 = Role::create(['name' => 'author', 'display_name' => ['en' => 'Author']]);

    // Assign Roles
    $this->actingAs($user, 'sanctum')
        ->postJson("/api/v1/users/{$target->id}/assign-roles", ['roles' => ['editor', 'author']])
        ->assertStatus(200);

    $this->assertTrue($target->fresh()->hasAllRoles(['editor', 'author']));

    // Remove Roles
    $this->actingAs($user, 'sanctum')
        ->postJson("/api/v1/users/{$target->id}/remove-roles", ['roles' => ['editor']])
        ->assertStatus(200);

    $this->assertFalse($target->fresh()->hasRole('editor'));
    $this->assertTrue($target->fresh()->hasRole('author'));

    // Sync Roles
    $this->actingAs($user, 'sanctum')
        ->postJson("/api/v1/users/{$target->id}/sync-roles", ['roles' => ['editor']])
        ->assertStatus(200);

    $this->assertTrue($target->fresh()->hasRole('editor'));
    $this->assertFalse($target->fresh()->hasRole('author'));
});
