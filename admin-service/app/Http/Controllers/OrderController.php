<?php

namespace App\Http\Controllers;

use App\Enums\OrderStatus;
use App\Http\Requests\Orders\OrderIndexRequest;
use App\Http\Requests\Orders\UpdateOrderStatusRequest;
use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class OrderController extends Controller
{
    private const PER_PAGE = 20;

    public function __construct(private readonly OrderService $orders) {}

    public function index(OrderIndexRequest $request): View
    {
        return view('orders.index', [
            'orders' => $this->orders->paginate($request->validated(), self::PER_PAGE),
            'statuses' => OrderStatus::cases(),
        ]);
    }

    public function show(Order $order): View
    {
        return view('orders.show', ['order' => $this->orders->withDetails($order)]);
    }

    public function updateStatus(UpdateOrderStatusRequest $request, Order $order): RedirectResponse
    {
        $order = $this->orders->changeStatus($order, $request->status(), $request->user());

        return redirect()->route('orders.show', $order)->with('success', "Order {$order->order_number} is now {$order->status->label()}.");
    }
}
