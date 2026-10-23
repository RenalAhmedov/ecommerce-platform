<?php

namespace Tests\Feature\Models;

use App\Enums\InventoryMovementReason;
use App\Enums\OrderStatus;
use App\Models\InventoryMovement;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class OrderTest extends TestCase
{
    public function test_order_casts_status_money_and_address(): void
    {
        $order = Order::factory()->create([
            'status' => OrderStatus::Paid,
            'total' => 25,
            'shipping_address' => ['city' => 'Sofia', 'country' => 'BG'],
        ])->fresh();

        $this->assertSame(OrderStatus::Paid, $order->status);
        $this->assertSame('25.00', $order->total);
        $this->assertSame(['city' => 'Sofia', 'country' => 'BG'], $order->shipping_address);
    }

    public function test_order_has_items_and_belongs_to_a_user(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->for($user)->create();
        OrderItem::factory()->count(2)->for($order)->create();

        $order->load('user', 'items.product');

        $this->assertTrue($order->user->is($user));
        $this->assertCount(2, $order->items);
        $this->assertInstanceOf(Product::class, $order->items->first()->product);
    }

    public function test_a_product_on_an_order_cannot_be_hard_deleted(): void
    {
        $item = OrderItem::factory()->create();

        $this->expectException(QueryException::class);
        $this->expectExceptionMessage('order_items_product_id_foreign');

        $item->product->forceDelete();
    }

    public function test_inventory_movements_have_no_updated_at_and_cast_the_reason(): void
    {
        $order = Order::factory()->create();
        $product = Product::factory()->create();

        $movement = InventoryMovement::create([
            'product_id' => $product->id,
            'order_id' => $order->id,
            'change' => -2,
            'reason' => InventoryMovementReason::Sale,
        ])->fresh();

        $this->assertSame(InventoryMovementReason::Sale, $movement->reason);
        $this->assertArrayNotHasKey('updated_at', $movement->getAttributes());
        $this->assertTrue($movement->order->is($order));
    }

    public function test_it_reads_timestamps_written_by_the_nest_api(): void
    {
        $user = User::factory()->create();
        $id = DB::table('orders')->insertGetId([
            'order_number' => 'ORD-NEST-1',
            'user_id' => $user->id,
            'subtotal' => 10,
            'total' => 10,
            'paid_at' => '2026-10-22 16:28:15.593998+00',
        ]);

        $order = Order::find($id);

        $this->assertSame(OrderStatus::Pending, $order->status);
        $this->assertSame('2026-10-22T16:28:15.593998Z', $order->paid_at->toIso8601ZuluString('microsecond'));
    }

    public function test_it_writes_timestamps_with_microseconds_and_timezone(): void
    {
        Carbon::setTestNow('2026-10-23 10:00:00.123456');

        $order = Order::factory()->create();

        $stored = DB::table('orders')->where('id', $order->id)->value('created_at');
        $this->assertStringStartsWith('2026-10-23 10:00:00.123456', $stored);

        Carbon::setTestNow();
    }
}
