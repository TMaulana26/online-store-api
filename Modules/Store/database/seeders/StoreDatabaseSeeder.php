<?php

declare(strict_types=1);

namespace Modules\Store\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Acl\Models\User;
use Modules\Store\Models\Order;
use Modules\Store\Models\Product;

class StoreDatabaseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Seed 5 Products
        $products = [
            [
                'name' => ['en' => 'Gaming Laptop', 'id' => 'Laptop Game'],
                'description' => ['en' => 'High performance gaming laptop', 'id' => 'Laptop game performa tinggi'],
                'stock' => 10,
                'price' => 1500.00,
                'flash_sale_price' => 1200.00,
                'flash_sale_start' => now()->subDay(),
                'flash_sale_end' => now()->addDays(2),
                'is_active' => true,
            ],
            [
                'name' => ['en' => 'Wireless Headphones', 'id' => 'Headphone Nirkabel'],
                'description' => ['en' => 'Active noise cancelling wireless headphones', 'id' => 'Headphone nirkabel pembatal bising aktif'],
                'stock' => 50,
                'price' => 200.00,
                'flash_sale_price' => 150.00,
                'flash_sale_start' => now()->addDay(),
                'flash_sale_end' => now()->addDays(3), // Inactive flash sale (future)
                'is_active' => true,
            ],
            [
                'name' => ['en' => 'Mechanical Keyboard', 'id' => 'Papan Ketik Mekanis'],
                'description' => ['en' => 'RGB backlit mechanical keyboard', 'id' => 'Papan ketik mekanis lampu latar RGB'],
                'stock' => 30,
                'price' => 100.00,
                'flash_sale_price' => null,
                'flash_sale_start' => null,
                'flash_sale_end' => null,
                'is_active' => true,
            ],
            [
                'name' => ['en' => 'Gaming Mouse', 'id' => 'Tetikus Game'],
                'description' => ['en' => 'Ultra lightweight gaming mouse', 'id' => 'Tetikus game ultra ringan'],
                'stock' => 40,
                'price' => 60.00,
                'flash_sale_price' => 45.00,
                'flash_sale_start' => now()->subDays(2),
                'flash_sale_end' => now()->subDay(), // Inactive flash sale (expired)
                'is_active' => true,
            ],
            [
                'name' => ['en' => 'Smart Watch', 'id' => 'Jam Tangan Pintar'],
                'description' => ['en' => 'Waterproof smart watch with fitness tracker', 'id' => 'Jam tangan pintar tahan air dengan pelacak kebugaran'],
                'stock' => 0,
                'price' => 250.00,
                'flash_sale_price' => null,
                'flash_sale_start' => null,
                'flash_sale_end' => null,
                'is_active' => false, // Inactive product
            ],
        ];

        $seededProducts = [];
        foreach ($products as $productData) {
            $seededProducts[] = Product::updateOrCreate(
                ['price' => $productData['price']], // Match on regular price or just create
                $productData
            );
        }

        // Seed 1 Order for default user if user exists
        $user = User::where('email', 'user@example.com')->first();
        if ($user) {
            $product = $seededProducts[0]; // Gaming Laptop

            // Check if user already has an order, if not, seed one
            $existingOrder = Order::where('user_id', $user->id)->first();
            if (! $existingOrder) {
                $order = Order::create([
                    'user_id' => $user->id,
                    'status' => 'completed',
                    'total_amount' => 1200.00, // Active flash sale price of Gaming Laptop
                    'is_active' => true,
                ]);

                $order->items()->create([
                    'product_id' => $product->id,
                    'quantity' => 1,
                    'price' => 1200.00,
                ]);
            }
        }
    }
}
