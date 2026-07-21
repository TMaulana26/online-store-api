<?php

declare(strict_types=1);

use Modules\Acl\Models\User;
use Modules\Store\Models\Order;
use Modules\Store\Models\Product;
use Spatie\Permission\Models\Role;

test('guests cannot access orders CRUD paths', function () {
    $this->getJson('/api/v1/orders')->assertStatus(401);
    $this->postJson('/api/v1/orders', [])->assertStatus(401);
    $this->putJson('/api/v1/orders/1', [])->assertStatus(401);
    $this->deleteJson('/api/v1/orders/1')->assertStatus(401);
});

test('authorized user can list orders', function () {
    $user = User::factory()->create();
    $order = Order::create(['user_id' => $user->id, 'status' => 'pending', 'total_amount' => 50.00]);

    $this->actingAs($user, 'sanctum')
        ->getJson('/api/v1/orders')
        ->assertStatus(200)
        ->assertJsonStructure([
            'success',
            'message',
            'data' => [
                '*' => ['id', 'user_id', 'status', 'total_amount', 'items', 'created_at', 'updated_at'],
            ],
        ]);
});

test('admin can list all orders', function () {
    $admin = User::factory()->create();
    $adminRole = Role::findOrCreate('admin', 'web');
    $admin->assignRole($adminRole);

    $user = User::factory()->create();
    $order = Order::create(['user_id' => $user->id, 'status' => 'completed', 'total_amount' => 100.00]);

    $this->actingAs($admin, 'sanctum')
        ->getJson('/api/v1/orders')
        ->assertStatus(200)
        ->assertJsonCount(1, 'data');
});

test('admin can create order with user_id and items', function () {
    $admin = User::factory()->create();
    $adminRole = Role::findOrCreate('admin', 'web');
    $admin->assignRole($adminRole);

    $user = User::factory()->create();
    $product = Product::factory()->create(['stock' => 10, 'price' => 50.00]);

    $payload = [
        'user_id' => $user->id,
        'status' => 'processing',
        'items' => [
            [
                'product_id' => $product->id,
                'quantity' => 2,
            ],
        ],
    ];

    $this->actingAs($admin, 'sanctum')
        ->postJson('/api/v1/orders', $payload)
        ->assertStatus(201)
        ->assertJsonPath('data.user_id', $user->id)
        ->assertJsonPath('data.status', 'processing')
        ->assertJsonPath('data.total_amount', '100.00');

    expect($product->fresh()->stock)->toBe(8);
});

test('admin can update order items with stock adjust', function () {
    $admin = User::factory()->create();
    $adminRole = Role::findOrCreate('admin', 'web');
    $admin->assignRole($adminRole);

    $user = User::factory()->create();
    $product1 = Product::factory()->create(['stock' => 10, 'price' => 20.00]);
    $product2 = Product::factory()->create(['stock' => 5, 'price' => 10.00]);

    // Create initial order
    $order = Order::create(['user_id' => $user->id, 'status' => 'pending', 'total_amount' => 40.00]);
    $order->items()->create(['product_id' => $product1->id, 'quantity' => 2, 'price' => 20.00]);
    $product1->decrement('stock', 2);

    $payload = [
        'status' => 'completed',
        'items' => [
            [
                'product_id' => $product2->id,
                'quantity' => 3,
            ],
        ],
    ];

    $this->actingAs($admin, 'sanctum')
        ->putJson("/api/v1/orders/{$order->id}", $payload)
        ->assertStatus(200)
        ->assertJsonPath('data.status', 'completed')
        ->assertJsonPath('data.total_amount', '30.00');

    // Product 1 stock refunded: 8 + 2 = 10
    expect($product1->fresh()->stock)->toBe(10);
    // Product 2 stock deducted: 5 - 3 = 2
    expect($product2->fresh()->stock)->toBe(2);
});

test('authorized user can toggle status, soft delete, restore, and force delete an order', function () {
    $user = User::factory()->create();
    $order = Order::create(['user_id' => $user->id, 'status' => 'pending', 'total_amount' => 50.00]);

    // Toggle Status
    $this->actingAs($user, 'sanctum')
        ->patchJson("/api/v1/orders/{$order->id}/toggle-status")
        ->assertStatus(200)
        ->assertJsonPath('data.is_active', false);

    // Soft Delete
    $this->actingAs($user, 'sanctum')
        ->deleteJson("/api/v1/orders/{$order->id}")
        ->assertStatus(200);

    $this->assertSoftDeleted('orders', ['id' => $order->id]);

    // Restore
    $this->actingAs($user, 'sanctum')
        ->patchJson("/api/v1/orders/{$order->id}/restore")
        ->assertStatus(200)
        ->assertJsonPath('data.deleted_at', null);

    $this->assertDatabaseHas('orders', ['id' => $order->id, 'deleted_at' => null]);

    // Soft delete again to force delete
    $order->delete();

    // Force Delete
    $this->actingAs($user, 'sanctum')
        ->deleteJson("/api/v1/orders/{$order->id}/force-delete")
        ->assertStatus(200);

    $this->assertDatabaseMissing('orders', ['id' => $order->id]);
});

test('authorized user can bulk manage orders', function () {
    $user = User::factory()->create();
    $order1 = Order::create(['user_id' => $user->id, 'status' => 'pending', 'total_amount' => 50.00, 'is_active' => true]);
    $order2 = Order::create(['user_id' => $user->id, 'status' => 'pending', 'total_amount' => 30.00, 'is_active' => true]);
    $ids = [$order1->id, $order2->id];

    // Bulk Delete
    $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/orders/bulk/delete', ['ids' => $ids])
        ->assertStatus(200)
        ->assertJsonCount(2, 'data.affected');

    foreach ($ids as $id) {
        $this->assertSoftDeleted('orders', ['id' => $id]);
    }

    // Bulk Restore
    $this->actingAs($user, 'sanctum')
        ->patchJson('/api/v1/orders/bulk/restore', ['ids' => $ids])
        ->assertStatus(200)
        ->assertJsonCount(2, 'data.affected');

    foreach ($ids as $id) {
        $this->assertDatabaseHas('orders', ['id' => $id, 'deleted_at' => null]);
    }

    // Bulk Toggle status
    $this->actingAs($user, 'sanctum')
        ->patchJson('/api/v1/orders/bulk/toggle-status', ['ids' => $ids])
        ->assertStatus(200)
        ->assertJsonCount(2, 'data.affected');

    foreach ($ids as $id) {
        $this->assertDatabaseHas('orders', ['id' => $id, 'is_active' => false]);
    }

    // Soft delete again to bulk force delete
    Order::whereIn('id', $ids)->delete();

    // Bulk Force Delete
    $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/orders/bulk/force-delete', ['ids' => $ids])
        ->assertStatus(200)
        ->assertJsonCount(2, 'data.affected');

    foreach ($ids as $id) {
        $this->assertDatabaseMissing('orders', ['id' => $id]);
    }
});
