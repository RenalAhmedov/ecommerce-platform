<?php

namespace App\Enums;

enum OrderStatus: string
{
    case Pending = 'pending';
    case Paid = 'paid';
    case Processing = 'processing';
    case Shipped = 'shipped';
    case Delivered = 'delivered';
    case Cancelled = 'cancelled';
    case PaymentFailed = 'payment_failed';

    /**
     * Statuses an admin may move an order to. Pending orders are left to the
     * Nest api-gateway: it is charging them, and cancelling one mid-payment
     * would leave a paid customer with a cancelled order (its expiry job
     * resolves stuck ones after 15 minutes). Final statuses have no next step.
     *
     * @return list<self>
     */
    public function adminTransitions(): array
    {
        return match ($this) {
            self::Paid => [self::Processing, self::Cancelled],
            self::Processing => [self::Shipped, self::Cancelled],
            self::Shipped => [self::Delivered],
            self::Pending, self::Delivered, self::Cancelled, self::PaymentFailed => [],
        };
    }

    public function canMoveTo(self $status): bool
    {
        return in_array($status, $this->adminTransitions(), true);
    }

    public function label(): string
    {
        return ucfirst(str_replace('_', ' ', $this->value));
    }
}
