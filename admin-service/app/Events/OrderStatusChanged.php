<?php

namespace App\Events;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\User;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;

/** An admin moved an order to another status; the other admins' pages update their badges. */
class OrderStatusChanged implements ShouldBroadcast
{
    use Dispatchable;

    public readonly int $orderId;

    public readonly string $orderNumber;

    public readonly OrderStatus $status;

    public readonly int $changedById;

    public readonly string $changedBy;

    public function __construct(Order $order, User $admin)
    {
        $this->orderId = $order->id;
        $this->orderNumber = $order->order_number;
        $this->status = $order->status;
        $this->changedById = $admin->id;
        $this->changedBy = $admin->name;
    }

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('admin.orders');
    }

    public function broadcastAs(): string
    {
        return 'order.status-changed';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return [
            'id' => $this->orderId,
            'orderNumber' => $this->orderNumber,
            'status' => $this->status->value,
            'statusLabel' => $this->status->label(),
            'statusClasses' => $this->status->badgeClasses(),
            'changedById' => $this->changedById,
            'changedBy' => $this->changedBy,
            'url' => route('orders.show', $this->orderId, absolute: false),
        ];
    }
}
