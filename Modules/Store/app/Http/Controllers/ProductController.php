<?php

declare(strict_types=1);

namespace Modules\Store\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Traits\HandlesIndexQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Store\Models\Product;
use Modules\Store\Transformers\StoreProductResource;

class ProductController extends Controller
{
    use HandlesIndexQuery;

    /**
     * Display a listing of the products resource.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Product::query()->active();

        $locale = app()->getLocale();

        // Query JSON translation values safely using Laravel JSON arrow paths
        $products = $this->handleIndexQuery(
            $query,
            $request->all(),
            ["name->{$locale}", "description->{$locale}"]
        );

        return $this->paginatedResponse(
            StoreProductResource::collection($products),
            'Products retrieved successfully.'
        );
    }

    /**
     * Display the specified product resource.
     */
    public function show(Product $product): JsonResponse
    {
        if (! $product->is_active) {
            return $this->errorResponse('Product is not active.', 404);
        }

        return $this->resourceResponse(
            new StoreProductResource($product),
            'Product details retrieved successfully.'
        );
    }
}
