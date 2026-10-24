<?php

namespace App\Events;

use App\Models\Order;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;

/** A customer paid an order in the shop (announced by the api-gateway). Queued: the worker sends it to Reverb. */
class OrderPaid implements ShouldBroadcast
{
    use Dispatchable;

    public readonly int $orderId;

    public readonly string $orderNumber;

    public readonly string $total;

    public readonly string $customer;

    public function __construct(Order $order)
    {
        $this->orderId = $order->id;
        $this->orderNumber = $order->order_number;
        $this->total = $order->total;
        $this->customer = $order->user->name;
    }

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('admin.orders');
    }

    public function broadcastAs(): string
    {
        return 'order.paid';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return [
            'id' => $this->orderId,
            'orderNumber' => $this->orderNumber,
            'total' => $this->total,
            'customer' => $this->customer,
            'url' => route('orders.show', $this->orderId, absolute: false),
        ];
    }
}
