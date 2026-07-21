<?php

declare(strict_types=1);

use Modules\Acl\Models\User;
use Modules\Store\Models\Product;

test('guests cannot access products CRUD routes', function () {
    $this->postJson('/api/v1/products', [])->assertStatus(401);
    $this->putJson('/api/v1/products/1', [])->assertStatus(401);
    $this->deleteJson('/api/v1/products/1')->assertStatus(401);
    $this->patchJson('/api/v1/products/1/toggle-status')->assertStatus(401);
});

test('authorized user can list products', function () {
    $user = User::factory()->create();
    Product::factory()->count(3)->create();

    $this->actingAs($user, 'sanctum')
        ->getJson('/api/v1/products')
        ->assertStatus(200)
        ->assertJsonStructure([
            'success',
            'message',
            'data' => [
                '*' => [
                    'id', 'name', 'description', 'stock', 'price',
                    'flash_sale_price', 'flash_sale_start', 'flash_sale_end',
                    'is_in_flash_sale', 'active_price', 'is_active',
                ],
            ],
            'links',
            'meta',
        ]);
});

test('authorized user can create product', function () {
    $user = User::factory()->create();
    $payload = [
        'name' => ['en' => 'Gaming Phone', 'id' => 'Ponsel Game'],
        'description' => ['en' => 'High refreshrate screen', 'id' => 'Layar dengan laju penyegaran tinggi'],
        'stock' => 15,
        'price' => 800.00,
    ];

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/products', $payload)
        ->assertStatus(201)
        ->assertJsonPath('data.name', 'Gaming Phone')
        ->assertJsonPath('data.stock', 15)
        ->assertJsonPath('data.price', '800.00');
});

test('authorized user can update product', function () {
    $user = User::factory()->create();
    $product = Product::factory()->create(['stock' => 10]);

    $payload = [
        'stock' => 25,
        'name' => ['en' => 'Updated Name', 'id' => 'Nama Baru'],
    ];

    $this->actingAs($user, 'sanctum')
        ->putJson("/api/v1/products/{$product->id}", $payload)
        ->assertStatus(200)
        ->assertJsonPath('data.stock', 25)
        ->assertJsonPath('data.name', 'Updated Name');
});

test('authorized user can toggle product status', function () {
    $user = User::factory()->create();
    $product = Product::factory()->create(['is_active' => true]);

    $this->actingAs($user, 'sanctum')
        ->patchJson("/api/v1/products/{$product->id}/toggle-status")
        ->assertStatus(200)
        ->assertJsonPath('data.is_active', false);
});

test('authorized user can soft delete, restore, and force delete a product', function () {
    $user = User::factory()->create();
    $product = Product::factory()->create();

    // Soft Delete
    $this->actingAs($user, 'sanctum')
        ->deleteJson("/api/v1/products/{$product->id}")
        ->assertStatus(200);

    $this->assertSoftDeleted('products', ['id' => $product->id]);

    // Restore
    $this->actingAs($user, 'sanctum')
        ->patchJson("/api/v1/products/{$product->id}/restore")
        ->assertStatus(200)
        ->assertJsonPath('data.deleted_at', null);

    $this->assertDatabaseHas('products', ['id' => $product->id, 'deleted_at' => null]);

    // Soft delete again to force delete
    $product->delete();

    // Force Delete
    $this->actingAs($user, 'sanctum')
        ->deleteJson("/api/v1/products/{$product->id}/force-delete")
        ->assertStatus(200);

    $this->assertDatabaseMissing('products', ['id' => $product->id]);
});

test('authorized user can perform bulk actions on products', function () {
    $user = User::factory()->create();
    $products = Product::factory()->count(3)->create(['is_active' => true]);
    $ids = $products->pluck('id')->toArray();

    // Bulk Delete
    $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/products/bulk/delete', ['ids' => $ids])
        ->assertStatus(200)
        ->assertJsonCount(3, 'data.affected');

    foreach ($ids as $id) {
        $this->assertSoftDeleted('products', ['id' => $id]);
    }

    // Bulk Restore
    $this->actingAs($user, 'sanctum')
        ->patchJson('/api/v1/products/bulk/restore', ['ids' => $ids])
        ->assertStatus(200)
        ->assertJsonCount(3, 'data.affected');

    foreach ($ids as $id) {
        $this->assertDatabaseHas('products', ['id' => $id, 'deleted_at' => null]);
    }

    // Bulk Toggle status
    $this->actingAs($user, 'sanctum')
        ->patchJson('/api/v1/products/bulk/toggle-status', ['ids' => $ids])
        ->assertStatus(200)
        ->assertJsonCount(3, 'data.affected');

    foreach ($ids as $id) {
        $this->assertDatabaseHas('products', ['id' => $id, 'is_active' => false]);
    }

    // Soft delete again to bulk force delete
    Product::whereIn('id', $ids)->delete();

    // Bulk Force Delete
    $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/products/bulk/force-delete', ['ids' => $ids])
        ->assertStatus(200)
        ->assertJsonCount(3, 'data.affected');

    foreach ($ids as $id) {
        $this->assertDatabaseMissing('products', ['id' => $id]);
    }
});
