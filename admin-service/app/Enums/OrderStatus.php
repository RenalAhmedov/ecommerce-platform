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

    // Pending orders are still being charged by the api-gateway; its expiry job resolves stuck ones.
    /** @return list<self> */
    public function adminTransitions(): array
    {
        return match ($this) {
            self::Paid => [self::Processing, self::Cancelled],
            self::Processing => [self::Shipped, self::Cancelled],
            self::Shipped => [self::Delivered],
            self::Pending, self::Delivered, self::Cancelled, self::PaymentFailed => [],
        };
    }

    /** @return list<self> */
    public static function revenueStatuses(): array
    {
        return [self::Paid, self::Processing, self::Shipped, self::Delivered];
    }

    public function canMoveTo(self $status): bool
    {
        return in_array($status, $this->adminTransitions(), true);
    }

    public function badgeClasses(): string
    {
        return match ($this) {
            self::Pending => 'bg-gray-100 text-gray-700',
            self::Paid => 'bg-blue-100 text-blue-800',
            self::Processing => 'bg-amber-100 text-amber-800',
            self::Shipped => 'bg-indigo-100 text-indigo-800',
            self::Delivered => 'bg-green-100 text-green-800',
            self::Cancelled => 'bg-red-100 text-red-800',
            self::PaymentFailed => 'bg-red-50 text-red-700',
        };
    }

    public function label(): string
    {
        return ucfirst(str_replace('_', ' ', $this->value));
    }
}
