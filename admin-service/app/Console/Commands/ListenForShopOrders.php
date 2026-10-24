<?php

namespace App\Console\Commands;

use App\Services\OrderService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

/**
 * Runs in the admin-events container. The api-gateway publishes order.paid on
 * Redis (its own email sender listens to the same channel); this turns each one
 * into a live notification in the panel. Redis pub/sub keeps no history, so a
 * message sent while this is down is simply missed: the orders page still shows
 * the order, only the toast is lost. If Redis drops, the command exits and
 * Docker restarts it.
 */
#[Signature('orders:listen')]
#[Description('Listen for orders paid in the shop and notify the admins live')]
class ListenForShopOrders extends Command
{
    public const CHANNEL = 'order.paid';

    public function handle(OrderService $orders): void
    {
        Log::info('Listening for shop orders', ['channel' => self::CHANNEL]);

        Redis::connection('events')->subscribe([self::CHANNEL], fn (string $message) => $this->onMessage($message, $orders));
    }

    /** The api-gateway sends {"pattern": "order.paid", "data": {"orderId": 1, ...}}. */
    public function onMessage(string $message, OrderService $orders): void
    {
        $orderId = json_decode($message, true)['data']['orderId'] ?? null;

        if (! is_int($orderId)) {
            Log::warning('Ignored a malformed order event', ['message' => mb_substr($message, 0, 500)]);

            return;
        }

        $orders->announcePaid($orderId);
    }
}
