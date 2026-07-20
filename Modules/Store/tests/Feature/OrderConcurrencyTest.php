<?php

declare(strict_types=1);

use GuzzleHttp\Promise\Utils;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Modules\Acl\Models\User;
use Modules\Store\Models\Order;
use Modules\Store\Models\Product;

test('concurrency purchase prevents overselling', function () {
    // 1. Seed a user and a product
    $user = User::create([
        'name' => 'Concurrency Buyer',
        'email' => 'concurrency@example.com',
        'password' => Hash::make('password123'),
        'is_active' => true,
    ]);

    $token = $user->createToken('test_token')->plainTextToken;

    $product = Product::create([
        'name' => ['en' => 'Flash Sale Item', 'id' => 'Barang Kilat'],
        'description' => ['en' => 'Limited stock product', 'id' => 'Stok terbatas'],
        'stock' => 10,
        'price' => 99.99,
        'is_active' => true,
    ]);

    $payload = [
        'items' => [
            [
                'product_id' => $product->id,
                'quantity' => 1,
            ],
        ],
    ];

    try {
        $promises = [];
        $totalRequests = 30;

        // 2. Trigger concurrent requests
        for ($i = 0; $i < $totalRequests; $i++) {
            $promises[] = Http::withoutVerifying()
                ->async()
                ->withToken($token)
                ->withHeaders([
                    'Accept' => 'application/json',
                    'Accept-Language' => 'en',
                ])
                ->post('https://online-store-api.test/api/v1/orders', $payload);
        }

        // Wait for responses
        $responses = Utils::unwrap($promises);

        $successCount = 0;
        $failureCount = 0;

        foreach ($responses as $response) {
            $status = $response->status();
            if ($status === 201) {
                $successCount++;
            } elseif ($status === 422 || $status === 400) {
                $failureCount++;
            }
        }

        // 3. Assertions
        expect($successCount)->toBe(10);
        expect($failureCount)->toBe(20);

        $freshProduct = Product::find($product->id);
        expect($freshProduct->stock)->toBe(0);

        $ordersCount = Order::where('user_id', $user->id)->count();
        expect($ordersCount)->toBe(10);

    } finally {
        // 4. Manual cleanup to keep database clean
        Order::where('user_id', $user->id)->delete();
        $user->tokens()->delete();
        $user->delete();
        $product->forceDelete();
    }
});
