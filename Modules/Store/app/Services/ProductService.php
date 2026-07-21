<?php

declare(strict_types=1);

namespace Modules\Store\Services;

use App\Traits\HandlesIndexQuery;
use Illuminate\Support\Facades\DB;
use Modules\Store\Models\Product;

class ProductService
{
    use HandlesIndexQuery;

    /**
     * Find a product by its ID.
     */
    public function findById(string $id): Product
    {
        return Product::findOrFail($id);
    }

    /**
     * Display a listing of products.
     */
    public function index(array $params)
    {
        $query = Product::query();

        if (isset($params['trashed'])) {
            if ($params['trashed'] === 'only') {
                $query->onlyTrashed();
            } elseif ($params['trashed'] === 'with') {
                $query->withTrashed();
            }
        }

        if (isset($params['status'])) {
            $query->where('is_active', $params['status'] === 'active');
        } elseif (! auth()->check() || ! auth()->user()->hasRole('admin')) {
            $query->active();
        }

        $locale = app()->getLocale();

        return $this->handleIndexQuery(
            $query,
            $params,
            ["name->{$locale}", "description->{$locale}"]
        );
    }

    /**
     * Store a newly created product.
     */
    public function store(array $data): Product
    {
        return DB::transaction(function () use ($data) {
            return Product::create($data);
        });
    }

    /**
     * Update the specified product.
     */
    public function update(Product $product, array $data): Product
    {
        return DB::transaction(function () use ($product, $data) {
            $product->update($data);

            return $product->refresh();
        });
    }

    /**
     * Delete (soft-delete) the specified product.
     */
    public function delete(Product $product): bool
    {
        return $product->delete();
    }

    /**
     * Toggle a single product's active status.
     */
    public function toggleStatus(Product $product): Product
    {
        return DB::transaction(function () use ($product) {
            $product->update(['is_active' => ! $product->is_active]);

            return $product->refresh();
        });
    }

    /**
     * Restore a single soft-deleted product.
     */
    public function restore(string $id): Product
    {
        return DB::transaction(function () use ($id) {
            $product = Product::onlyTrashed()->findOrFail($id);
            $product->restore();

            return $product->refresh();
        });
    }

    /**
     * Force delete a single soft-deleted product.
     */
    public function forceDelete(string $id): Product
    {
        return DB::transaction(function () use ($id) {
            $product = Product::onlyTrashed()->findOrFail($id);
            $productData = clone $product;
            $product->forceDelete();

            return $productData;
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
                'toggle' => Product::query(),
                'restore',
                'forceDelete' => Product::onlyTrashed(),
                default => throw new \InvalidArgumentException("Invalid operation: {$operation}"),
            };

            $products = $query->whereIn('id', $ids)->get();
            $foundIds = $products->pluck('id')->toArray();
            $notFoundIds = array_values(array_diff($ids, $foundIds));

            if ($products->isNotEmpty()) {
                match ($operation) {
                    'delete' => Product::whereIn('id', $foundIds)->delete(),
                    'restore' => Product::onlyTrashed()->whereIn('id', $foundIds)->restore(),
                    'forceDelete' => Product::onlyTrashed()->whereIn('id', $foundIds)->forceDelete(),
                    'toggle' => $products->each(fn ($p) => $p->update(['is_active' => ! $p->is_active])),
                };

                if ($operation !== 'forceDelete') {
                    $products->each->refresh();
                }
            }

            return [
                'affected' => $products,
                'failed_ids' => $notFoundIds,
            ];
        });
    }
}
