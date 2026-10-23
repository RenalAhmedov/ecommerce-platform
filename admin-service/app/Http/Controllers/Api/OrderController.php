<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Orders\OrderIndexRequest;
use App\Http\Requests\Orders\UpdateOrderStatusRequest;
use App\Http\Resources\OrderCollection;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Services\OrderService;

class OrderController extends Controller
{
    private const DEFAULT_LIMIT = 20;

    public function __construct(private readonly OrderService $orders) {}

    public function index(OrderIndexRequest $request): OrderCollection
    {
        return new OrderCollection(
            $this->orders->paginate($request->validated(), $request->integer('limit', self::DEFAULT_LIMIT)),
        );
    }

    public function show(Order $order): OrderResource
    {
        return new OrderResource($this->orders->withDetails($order));
    }

    public function updateStatus(UpdateOrderStatusRequest $request, Order $order): OrderResource
    {
        $order = $this->orders->changeStatus($order, $request->status(), $request->user());

        return new OrderResource($this->orders->withDetails($order));
    }
}
