<?php

declare(strict_types=1);

namespace Modules\Store\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Traits\HandlesIndexQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Acl\Models\User;
use Modules\Store\Actions\CheckoutAction;
use Modules\Store\Http\Requests\StoreOrderRequest;
use Modules\Store\Models\Order;
use Modules\Store\Transformers\OrderResource;

class OrderController extends Controller
{
    use HandlesIndexQuery;

    public function __construct(
        protected CheckoutAction $checkoutAction
    ) {}

    /**
     * Display a listing of the user's orders.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Order::where('user_id', auth()->id())
            ->with(['items.product']);

        $orders = $this->handleIndexQuery(
            $query,
            $request->all(),
            ['status']
        );

        return $this->paginatedResponse(
            OrderResource::collection($orders),
            'Orders retrieved successfully.'
        );
    }

    /**
     * Store a newly created order.
     */
    public function store(StoreOrderRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = auth()->user();

        $order = $this->checkoutAction->execute(
            $user,
            $request->validated()['items']
        );

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
        if ($order->user_id !== auth()->id()) {
            return $this->errorResponse('Access denied.', 403);
        }

        return $this->resourceResponse(
            new OrderResource($order->load(['items.product'])),
            'Order details retrieved successfully.'
        );
    }
}
