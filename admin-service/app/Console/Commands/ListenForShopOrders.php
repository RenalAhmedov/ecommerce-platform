<?php

namespace App\Console\Commands;

use App\Services\OrderService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

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
