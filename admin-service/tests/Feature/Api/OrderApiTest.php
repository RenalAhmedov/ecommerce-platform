<?php

namespace Tests\Feature\Api;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\OrderItem;
use Tests\Feature\Api\Concerns\AuthenticatesWithNestToken;
use Tests\Feature\FeatureTestCase;

class OrderApiTest extends FeatureTestCase
{
    use AuthenticatesWithNestToken;

    public function test_orders_list_and_detail_use_the_nest_shape(): void
    {
        $order = Order::factory()->create(['status' => OrderStatus::Paid]);
        OrderItem::factory()->for($order)->create(['product_name' => 'Mug', 'quantity' => 2, 'unit_price' => 4.5, 'line_total' => 9]);

        $this->api()->getJson('/admin/api/orders?status=paid')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.orderNumber', $order->order_number)
            ->assertJsonPath('data.0.itemsCount', 1)
            ->assertJsonMissingPath('data.0.items');

        $this->api()->getJson("/admin/api/orders/{$order->id}")
            ->assertOk()
            ->assertJsonPath('data.allowedStatuses', ['processing', 'cancelled'])
            ->assertJsonPath('data.customer.email', $order->user->email)
            ->assertJsonPath('data.items.0', fn (array $item) => $item['productName'] === 'Mug' && $item['quantity'] === 2 && $item['unitPrice'] === '4.50' && $item['lineTotal'] === '9.00');
    }

    public function test_order_status_changes_through_the_api(): void
    {
        $order = Order::factory()->create(['status' => OrderStatus::Paid]);

        $this->api()->patchJson("/admin/api/orders/{$order->id}/status", ['status' => 'processing'])
            ->assertOk()
            ->assertJsonPath('data.status', 'processing')
            ->assertJsonPath('data.allowedStatuses', ['shipped', 'cancelled']);

        $this->api()->patchJson("/admin/api/orders/{$order->id}/status", ['status' => 'delivered'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');
    }
}
