<?php

namespace Tests\Feature\Broadcasting;

use App\Console\Commands\ListenForShopOrders;
use App\Enums\OrderStatus;
use App\Events\OrderPaid;
use App\Events\OrderStatusChanged;
use App\Models\Order;
use App\Models\User;
use App\Services\OrderService;
use Illuminate\Support\Facades\Event;
use Tests\Feature\FeatureTestCase;

class OrderBroadcastsTest extends FeatureTestCase
{
    public function test_a_status_change_in_the_panel_is_broadcast_to_the_other_admins(): void
    {
        Event::fake([OrderStatusChanged::class]);
        $admin = User::factory()->admin()->create(['name' => 'Grace']);
        $order = Order::factory()->create(['status' => OrderStatus::Paid]);

        $this->actingAs($admin)->patch("/orders/{$order->id}/status", ['status' => 'processing'])->assertSessionHasNoErrors();

        Event::assertDispatched(OrderStatusChanged::class, function (OrderStatusChanged $event) use ($order, $admin) {
            $this->assertSame('private-admin.orders', $event->broadcastOn()->name);
            $this->assertSame('order.status-changed', $event->broadcastAs());
            $this->assertSame([
                'id' => $order->id,
                'orderNumber' => $order->order_number,
                'status' => 'processing',
                'statusLabel' => 'Processing',
                'statusClasses' => OrderStatus::Processing->badgeClasses(),
                'changedById' => $admin->id,
                'changedBy' => 'Grace',
                'url' => "/orders/{$order->id}",
            ], $event->broadcastWith());

            return true;
        });
    }

    /** The exact message the api-gateway's ClientRedis publishes. */
    public function test_an_order_paid_in_the_shop_becomes_a_live_notification(): void
    {
        Event::fake([OrderPaid::class]);
        $order = Order::factory()->for(User::factory()->state(['name' => 'Ada']))->create(['status' => OrderStatus::Paid]);
        $message = json_encode(['pattern' => 'order.paid', 'data' => ['orderId' => $order->id, 'orderNumber' => $order->order_number, 'userId' => $order->user_id, 'total' => $order->total, 'paymentReference' => 'mock_1']]);

        app(ListenForShopOrders::class)->onMessage($message, app(OrderService::class));

        Event::assertDispatched(OrderPaid::class, fn (OrderPaid $event) => $event->orderId === $order->id
            && $event->customer === 'Ada'
            && $event->broadcastAs() === 'order.paid');
    }

    public function test_malformed_messages_and_unknown_orders_are_ignored(): void
    {
        Event::fake([OrderPaid::class]);
        $listener = app(ListenForShopOrders::class);

        foreach (['not json', '{"pattern":"order.paid"}', '{"data":{"orderId":"7"}}', '{"data":{"orderId":999999}}'] as $message) {
            $listener->onMessage($message, app(OrderService::class));
        }

        Event::assertNotDispatched(OrderPaid::class);
    }
}
