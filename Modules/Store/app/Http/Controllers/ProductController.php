<?php

declare(strict_types=1);

namespace Modules\Store\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Traits\HandlesBulkAndSoftDeletes;
use Illuminate\Http\JsonResponse;
use Modules\Store\Http\Requests\Product\IndexProductRequest;
use Modules\Store\Http\Requests\Product\StoreProductRequest;
use Modules\Store\Http\Requests\Product\UpdateProductRequest;
use Modules\Store\Models\Product;
use Modules\Store\Services\ProductService;
use Modules\Store\Transformers\StoreProductResource;

class ProductController extends Controller
{
    use HandlesBulkAndSoftDeletes;

    public function __construct(
        protected ProductService $productService
    ) {}

    protected function getService()
    {
        return $this->productService;
    }

    protected function getResourceClass(): string
    {
        return StoreProductResource::class;
    }

    protected function getModelName(): string
    {
        return 'product';
    }

    protected function getEagerLoadRelations(): array
    {
        return [];
    }

    /**
     * Display a listing of the products resource.
     */
    public function index(IndexProductRequest $request): JsonResponse
    {
        $products = $this->productService->index($request->validated());

        return $this->paginatedResponse(
            StoreProductResource::collection($products),
            'Products retrieved successfully.'
        );
    }

    /**
     * Store a newly created product.
     */
    public function store(StoreProductRequest $request): JsonResponse
    {
        $product = $this->productService->store($request->validated());

        return $this->resourceResponse(
            new StoreProductResource($product),
            'Product created successfully.',
            201
        );
    }

    /**
     * Display the specified product.
     */
    public function show(Product $product): JsonResponse
    {
        if (! auth()->check() && ! $product->is_active) {
            return $this->errorResponse('Product is not active.', 404);
        }

        return $this->resourceResponse(
            new StoreProductResource($product),
            'Product details retrieved successfully.'
        );
    }

    /**
     * Update the specified product.
     */
    public function update(UpdateProductRequest $request, Product $product): JsonResponse
    {
        $product = $this->productService->update($product, $request->validated());

        return $this->resourceResponse(
            new StoreProductResource($product),
            'Product updated successfully.'
        );
    }

    /**
     * Remove the specified product (Soft Delete).
     */
    public function destroy(Product $product): JsonResponse
    {
        $this->productService->delete($product);

        return $this->resourceResponse(
            new StoreProductResource($product),
            'Product deleted successfully.'
        );
    }

    /**
     * Toggle the active status of the specified product.
     */
    public function toggleStatus(Product $product): JsonResponse
    {
        $product = $this->productService->toggleStatus($product);

        return $this->resourceResponse(
            new StoreProductResource($product),
            'Product status toggled successfully.'
        );
    }
}
