<?php

declare(strict_types=1);

namespace Modules\Store\Services;

use App\Traits\HandlesIndexQuery;
use Illuminate\Support\Facades\DB;
use Modules\Store\Exceptions\OutOfStockException;
use Modules\Store\Models\Order;
use Modules\Store\Models\Product;

class OrderService
{
    use HandlesIndexQuery;

    /**
     * Find an order by its ID.
     */
    public function findById(string $id): Order
    {
        return Order::findOrFail($id);
    }

    /**
     * Display a listing of orders.
     */
    public function index(array $params)
    {
        $query = Order::query();

        if (isset($params['trashed'])) {
            if ($params['trashed'] === 'only') {
                $query->onlyTrashed();
            } elseif ($params['trashed'] === 'with') {
                $query->withTrashed();
            }
        }

        if (isset($params['status'])) {
            // Check status as general string filter or boolean active
            if ($params['status'] === 'active') {
                $query->where('is_active', true);
            } elseif ($params['status'] === 'inactive') {
                $query->where('is_active', false);
            } else {
                $query->where('status', $params['status']);
            }
        }

        return $this->handleIndexQuery(
            $query,
            $params,
            ['status']
        );
    }

    /**
     * Store a newly created order.
     */
    public function store(array $data): Order
    {
        return DB::transaction(function () use ($data) {
            $totalAmount = 0.0;
            $itemsData = [];

            if (empty($data['items'])) {
                throw new \InvalidArgumentException('An order must consist of at minimum one order item.');
            }

            foreach ($data['items'] as $item) {
                $productId = $item['product_id'];
                $quantity = $item['quantity'];

                $product = Product::where('id', $productId)->lockForUpdate()->firstOrFail();

                if ($product->stock < $quantity) {
                    $productName = $product->getTranslation('name', 'en') ?: $product->name;
                    throw new OutOfStockException("Insufficient stock for product: {$productName}.");
                }

                $product->decrement('stock', $quantity);

                $price = (float) $product->getActivePrice();
                $totalAmount += $price * $quantity;

                $itemsData[] = [
                    'product_id' => $productId,
                    'quantity' => $quantity,
                    'price' => $price,
                ];
            }

            $order = Order::create([
                'user_id' => $data['user_id'],
                'status' => $data['status'] ?? 'pending',
                'is_active' => $data['is_active'] ?? true,
                'total_amount' => $totalAmount,
            ]);

            foreach ($itemsData as $itemData) {
                $order->items()->create($itemData);
            }

            return $order->load('items.product');
        });
    }

    /**
     * Update the specified order.
     */
    public function update(Order $order, array $data): Order
    {
        return DB::transaction(function () use ($order, $data) {
            if (isset($data['items'])) {
                // Refund old items stock
                foreach ($order->items as $oldItem) {
                    $product = Product::find($oldItem->product_id);
                    if ($product) {
                        $product->increment('stock', $oldItem->quantity);
                    }
                }

                // Delete old items
                $order->items()->delete();

                // Process new items and deduct stock
                $totalAmount = 0.0;
                $itemsData = [];

                foreach ($data['items'] as $item) {
                    $productId = $item['product_id'];
                    $quantity = $item['quantity'];

                    $product = Product::where('id', $productId)->lockForUpdate()->firstOrFail();

                    if ($product->stock < $quantity) {
                        $productName = $product->getTranslation('name', 'en') ?: $product->name;
                        throw new OutOfStockException("Insufficient stock for product: {$productName}.");
                    }

                    $product->decrement('stock', $quantity);

                    $price = (float) $product->getActivePrice();
                    $totalAmount += $price * $quantity;

                    $itemsData[] = [
                        'product_id' => $productId,
                        'quantity' => $quantity,
                        'price' => $price,
                    ];
                }

                $data['total_amount'] = $totalAmount;

                foreach ($itemsData as $itemData) {
                    $order->items()->create($itemData);
                }
            }

            unset($data['items']);

            $order->update($data);

            return $order->refresh()->load('items.product');
        });
    }

    /**
     * Delete (soft-delete) the specified order.
     */
    public function delete(Order $order): bool
    {
        return $order->delete();
    }

    /**
     * Toggle a single order's active status.
     */
    public function toggleStatus(Order $order): Order
    {
        return DB::transaction(function () use ($order) {
            $order->update(['is_active' => ! $order->is_active]);

            return $order->refresh();
        });
    }

    /**
     * Restore a single soft-deleted order.
     */
    public function restore(string $id): Order
    {
        return DB::transaction(function () use ($id) {
            $order = Order::onlyTrashed()->findOrFail($id);
            $order->restore();

            return $order->refresh();
        });
    }

    /**
     * Force delete a single soft-deleted order.
     */
    public function forceDelete(string $id): Order
    {
        return DB::transaction(function () use ($id) {
            $order = Order::onlyTrashed()->findOrFail($id);
            $orderData = clone $order;
            $order->forceDelete();

            return $orderData;
        });
    }

    /**
     * Perform bulk operations.
     */
    public function handleBulkOperation(array $ids, string $operation): array
    {
        return DB::transaction(function () use ($ids, $operation) {
            $query = match ($operation) {
                'delete',
                'toggle' => Order::query(),
                'restore',
                'forceDelete' => Order::onlyTrashed(),
                default => throw new \InvalidArgumentException("Invalid operation: {$operation}"),
            };

            $orders = $query->whereIn('id', $ids)->get();
            $foundIds = $orders->pluck('id')->toArray();
            $notFoundIds = array_values(array_diff($ids, $foundIds));

            if ($orders->isNotEmpty()) {
                match ($operation) {
                    'delete' => Order::whereIn('id', $foundIds)->delete(),
                    'restore' => Order::onlyTrashed()->whereIn('id', $foundIds)->restore(),
                    'forceDelete' => Order::onlyTrashed()->whereIn('id', $foundIds)->forceDelete(),
                    'toggle' => $orders->each(fn ($o) => $o->update(['is_active' => ! $o->is_active])),
                };

                if ($operation !== 'forceDelete') {
                    $orders->each->refresh();
                }
            }

            return [
                'affected' => $orders,
                'failed_ids' => $notFoundIds,
            ];
        });
    }
}
