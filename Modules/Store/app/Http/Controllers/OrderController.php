<?php

declare(strict_types=1);

namespace Modules\Store\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Traits\HandlesBulkAndSoftDeletes;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Store\Actions\CheckoutAction;
use Modules\Store\Http\Requests\Order\IndexOrderRequest;
use Modules\Store\Http\Requests\Order\UpdateOrderRequest;
use Modules\Store\Models\Order;
use Modules\Store\Services\OrderService;
use Modules\Store\Transformers\OrderResource;

class OrderController extends Controller
{
    use HandlesBulkAndSoftDeletes;

    public function __construct(
        protected OrderService $orderService,
        protected CheckoutAction $checkoutAction
    ) {}

    protected function getService()
    {
        return $this->orderService;
    }

    protected function getResourceClass(): string
    {
        return OrderResource::class;
    }

    protected function getModelName(): string
    {
        return 'order';
    }

    protected function getEagerLoadRelations(): array
    {
        return ['items.product'];
    }

    /**
     * Display a listing of the orders.
     */
    public function index(IndexOrderRequest $request): JsonResponse
    {
        // For admin listing vs user listing:
        // Admin gets all orders based on parameters.
        // Let's check: if we want to restrict standard users to only see their own orders,
        // we can filter the query by user_id if they don't have manage permissions.
        // Since we don't have direct role checking in controller, we can do it by checking if user has role/permission,
        // or check if request is for admin CRUD. Let's filter by auth()->id() if user is not admin.
        $params = $request->validated();
        if (auth()->check() && ! auth()->user()->hasRole('admin')) {
            $params['user_id'] = auth()->id();
        }

        $orders = $this->orderService->index($params);
        $orders->load(['items.product']);

        return $this->paginatedResponse(
            OrderResource::collection($orders),
            'Orders retrieved successfully.'
        );
    }

    /**
     * Store a newly created order (handles both admin CRUD and checkout).
     */
    public function store(Request $request): JsonResponse
    {
        if ($request->has('user_id')) {
            $validated = $request->validate([
                'user_id' => 'required|exists:users,id',
                'status' => 'required|string|max:255',
                'is_active' => 'boolean|nullable',
                'items' => 'required|array|min:1',
                'items.*.product_id' => 'required|exists:products,id',
                'items.*.quantity' => 'required|integer|min:1',
            ]);
            $order = $this->orderService->store($validated);
        } else {
            $validated = $request->validate([
                'items' => 'required|array|min:1',
                'items.*.product_id' => 'required|exists:products,id',
                'items.*.quantity' => 'required|integer|min:1',
            ]);
            $order = $this->checkoutAction->execute(
                auth()->user(),
                $validated['items']
            );
        }

        return $this->resourceResponse(
            new OrderResource($order),
            'Order created successfully.',
            201
        );
    }

    /**
     * Display the specified order.
     */
    public function show(Order $order): JsonResponse
    {
        if (auth()->check() && ! auth()->user()->hasRole('admin') && $order->user_id !== auth()->id()) {
            return $this->errorResponse('Access denied.', 403);
        }

        return $this->resourceResponse(
            new OrderResource($order->load(['items.product'])),
            'Order details retrieved successfully.'
        );
    }

    /**
     * Update the specified order.
     */
    public function update(UpdateOrderRequest $request, Order $order): JsonResponse
    {
        $order = $this->orderService->update($order, $request->validated());

        return $this->resourceResponse(
            new OrderResource($order),
            'Order updated successfully.'
        );
    }

    /**
     * Remove the specified order (Soft Delete).
     */
    public function destroy(Order $order): JsonResponse
    {
        $this->orderService->delete($order);

        return $this->resourceResponse(
            new OrderResource($order),
            'Order deleted successfully.'
        );
    }

    /**
     * Toggle active status.
     */
    public function toggleStatus(Order $order): JsonResponse
    {
        $order = $this->orderService->toggleStatus($order);

        return $this->resourceResponse(
            new OrderResource($order),
            'Order status toggled successfully.'
        );
    }
}
