<?php

declare(strict_types=1);

namespace Modules\Store\Actions;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Modules\Acl\Models\User;
use Modules\Store\Exceptions\OutOfStockException;
use Modules\Store\Models\Order;
use Modules\Store\Models\Product;

class CheckoutAction
{
    /**
     * Execute the checkout action.
     *
     * @param  array  $items  [['product_id' => int, 'quantity' => int]]
     *
     * @throws OutOfStockException
     * @throws ModelNotFoundException
     * @throws \InvalidArgumentException
     */
    public function execute(User $user, array $items): Order
    {
        if (empty($items)) {
            throw new \InvalidArgumentException('An order must consist of at minimum one order item.');
        }

        return DB::transaction(function () use ($user, $items) {
            // Group quantities by product ID to handle duplicate product inputs in a single payload
            $groupedItems = [];
            foreach ($items as $item) {
                if (! isset($item['product_id']) || ! isset($item['quantity'])) {
                    throw new \InvalidArgumentException('Each item must contain product_id and quantity.');
                }

                $productId = (int) $item['product_id'];
                $quantity = (int) $item['quantity'];

                if ($quantity <= 0) {
                    throw new \InvalidArgumentException('Quantity must be greater than zero.');
                }

                if (isset($groupedItems[$productId])) {
                    $groupedItems[$productId] += $quantity;
                } else {
                    $groupedItems[$productId] = $quantity;
                }
            }

            $totalAmount = 0.0;
            $orderItemsData = [];

            // Iterate and lock each product row to prevent race conditions
            foreach ($groupedItems as $productId => $quantity) {
                /** @var Product|null $product */
                $product = Product::where('id', $productId)->lockForUpdate()->first();

                if (! $product) {
                    throw (new ModelNotFoundException)->setModel(Product::class, [$productId]);
                }

                if ($product->stock < $quantity) {
                    // Fetch English name or first available translation
                    $productName = $product->getTranslation('name', 'en') ?: $product->name;
                    throw new OutOfStockException("Insufficient stock for product: {$productName}.");
                }

                // Decrement stock atomically
                $product->decrement('stock', $quantity);

                // Fetch correct active price (incorporating flash sales)
                $itemPrice = (float) $product->getActivePrice();
                $itemSubtotal = $itemPrice * $quantity;
                $totalAmount += $itemSubtotal;

                $orderItemsData[] = [
                    'product_id' => $product->id,
                    'quantity' => $quantity,
                    'price' => $itemPrice,
                ];
            }

            // Create Order
            $order = Order::create([
                'user_id' => $user->id,
                'status' => 'completed',
                'total_amount' => $totalAmount,
            ]);

            // Create Order Items
            foreach ($orderItemsData as $itemData) {
                $order->items()->create($itemData);
            }

            return $order->load(['items.product']);
        });
    }
}
