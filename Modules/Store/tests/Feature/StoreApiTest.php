<?php

declare(strict_types=1);

use Modules\Acl\Models\User;
use Modules\Store\Models\Product;

test('guest can list active products', function () {
    Product::factory()->count(3)->create(['is_active' => true]);
    Product::factory()->create(['is_active' => false]);

    $this->getJson('/api/v1/products')
        ->assertStatus(200)
        ->assertJsonCount(3, 'data');
});

test('guest can see product details', function () {
    $product = Product::factory()->create([
        'name' => ['en' => 'Test Product', 'id' => 'Produk Tes'],
        'is_active' => true,
    ]);

    $this->getJson("/api/v1/products/{$product->id}")
        ->assertStatus(200)
        ->assertJsonPath('data.name', 'Test Product');
});

test('guest cannot see inactive product details', function () {
    $product = Product::factory()->create(['is_active' => false]);

    $this->getJson("/api/v1/products/{$product->id}")
        ->assertStatus(404);
});

test('authenticated user can place an order', function () {
    $user = User::factory()->create();
    $product = Product::factory()->create(['stock' => 5, 'price' => 10.00]);

    $payload = [
        'items' => [
            [
                'product_id' => $product->id,
                'quantity' => 2,
            ],
        ],
    ];

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/orders', $payload)
        ->assertStatus(201)
        ->assertJsonPath('data.total_amount', '20.00')
        ->assertJsonCount(1, 'data.items');

    expect($product->fresh()->stock)->toBe(3);
});

test('order placement fails with empty items', function () {
    $user = User::factory()->create();

    $payload = [
        'items' => [],
    ];

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/orders', $payload)
        ->assertStatus(422)
        ->assertJsonValidationErrors(['items']);
});

test('flash sale price applies during active window', function () {
    $user = User::factory()->create();
    $product = Product::factory()->create([
        'price' => 100.00,
        'flash_sale_price' => 49.99,
        'flash_sale_start' => now()->subHour(),
        'flash_sale_end' => now()->addHour(),
        'stock' => 10,
    ]);

    $payload = [
        'items' => [
            [
                'product_id' => $product->id,
                'quantity' => 1,
            ],
        ],
    ];

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/orders', $payload)
        ->assertStatus(201)
        ->assertJsonPath('data.total_amount', '49.99');
});

test('regular price applies outside active flash sale window', function () {
    $user = User::factory()->create();
    $product = Product::factory()->create([
        'price' => 100.00,
        'flash_sale_price' => 49.99,
        'flash_sale_start' => now()->addHour(),
        'flash_sale_end' => now()->addHours(2),
        'stock' => 10,
    ]);

    $payload = [
        'items' => [
            [
                'product_id' => $product->id,
                'quantity' => 1,
            ],
        ],
    ];

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/orders', $payload)
        ->assertStatus(201)
        ->assertJsonPath('data.total_amount', '100.00');
});
