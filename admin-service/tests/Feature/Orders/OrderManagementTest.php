<?php

namespace Tests\Feature\Orders;

use App\Enums\InventoryMovementReason;
use App\Enums\OrderStatus;
use App\Models\Inventory;
use App\Models\InventoryMovement;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class OrderManagementTest extends TestCase
{
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();
    }

    /** A paid order for 2 × product A and 3 × product B, with 10 of each left in stock. */
    private function paidOrder(OrderStatus $status = OrderStatus::Paid): array
    {
        $order = Order::factory()->create(['status' => $status]);
        $products = Product::factory()->count(2)->create()->each(fn (Product $product) => Inventory::factory()->for($product)->create(['quantity' => 10]));
        OrderItem::factory()->for($order)->for($products[0])->create(['quantity' => 2]);
        OrderItem::factory()->for($order)->for($products[1])->create(['quantity' => 3]);

        return [$order, $products];
    }

    private function changeStatus(Order $order, OrderStatus|string $status): TestResponse
    {
        return $this->actingAs($this->admin)->patch("/orders/{$order->id}/status", [
            'status' => $status instanceof OrderStatus ? $status->value : $status,
        ]);
    }

    public function test_customers_cannot_see_orders(): void
    {
        $this->actingAs(User::factory()->create())->get('/orders')->assertRedirect(route('login', absolute: false));
    }

    public function test_the_list_filters_by_status_search_and_dates(): void
    {
        $ada = User::factory()->create(['name' => 'Ada Lovelace', 'email' => 'ada@example.test']);
        $match = Order::factory()->for($ada)->create(['status' => OrderStatus::Shipped, 'created_at' => '2026-10-10 12:00:00']);
        $wrongStatus = Order::factory()->for($ada)->create(['status' => OrderStatus::Paid, 'created_at' => '2026-10-10 12:00:00']);
        $wrongDate = Order::factory()->for($ada)->create(['status' => OrderStatus::Shipped, 'created_at' => '2026-10-12 00:00:00']);
        $otherCustomer = Order::factory()->create(['status' => OrderStatus::Shipped, 'created_at' => '2026-10-10 12:00:00']);

        $this->actingAs($this->admin)
            ->get('/orders?search=lovelace&status=shipped&date_from=2026-10-10&date_to=2026-10-11')
            ->assertOk()
            ->assertSee($match->order_number)
            ->assertDontSee($wrongStatus->order_number)
            ->assertDontSee($wrongDate->order_number)
            ->assertDontSee($otherCustomer->order_number);

        $this->actingAs($this->admin)
            ->get('/orders?search='.$otherCustomer->order_number)
            ->assertSee($otherCustomer->order_number)
            ->assertDontSee($match->order_number);
    }

    public function test_searching_a_name_only_matches_that_orders_customer(): void
    {
        $ada = User::factory()->create(['name' => 'Ada']);
        $order = Order::factory()->for($ada)->create();
        $unrelated = Order::factory()->create();

        $this->actingAs($this->admin)->get('/orders?search=Ada')->assertSee($order->order_number)->assertDontSee($unrelated->order_number);
    }

    public function test_an_invalid_date_range_is_rejected(): void
    {
        $this->actingAs($this->admin)->get('/orders?date_from=2026-10-10&date_to=2026-10-01')->assertSessionHasErrors('date_to');
    }

    public function test_orders_of_deleted_customers_still_show(): void
    {
        $order = Order::factory()->create();
        $order->user->delete();

        $this->actingAs($this->admin)->get('/orders')->assertOk()->assertSee($order->order_number);
        $this->actingAs($this->admin)->get("/orders/{$order->id}")->assertOk()->assertSee('(deleted)');
    }

    public function test_the_detail_page_shows_items_and_the_allowed_next_steps(): void
    {
        [$order] = $this->paidOrder();

        $this->actingAs($this->admin)
            ->get("/orders/{$order->id}")
            ->assertOk()
            ->assertSee($order->items->first()->product_name)
            ->assertSee('Mark as processing')
            ->assertSee('Mark as cancelled')
            ->assertDontSee('Mark as delivered');
    }

    public function test_an_order_moves_through_fulfilment(): void
    {
        [$order] = $this->paidOrder();

        foreach ([OrderStatus::Processing, OrderStatus::Shipped, OrderStatus::Delivered] as $status) {
            $this->changeStatus($order, $status)->assertSessionHasNoErrors()->assertRedirect("/orders/{$order->id}");
            $this->assertSame($status, $order->refresh()->status);
        }

        $this->assertSame(0, InventoryMovement::count(), 'fulfilment does not touch stock');
    }

    public function test_steps_cannot_be_skipped_or_reversed(): void
    {
        $cases = [
            [OrderStatus::Paid, OrderStatus::Delivered],
            [OrderStatus::Shipped, OrderStatus::Cancelled],
            [OrderStatus::Delivered, OrderStatus::Processing],
            [OrderStatus::Cancelled, OrderStatus::Paid],
            [OrderStatus::Pending, OrderStatus::Cancelled],
            [OrderStatus::PaymentFailed, OrderStatus::Processing],
        ];

        foreach ($cases as [$from, $to]) {
            $order = Order::factory()->create(['status' => $from]);

            $this->changeStatus($order, $to)->assertSessionHasErrors('status');
            $this->assertSame($from, $order->refresh()->status, "{$from->value} → {$to->value} must be refused");
        }
    }

    public function test_cancelling_returns_the_stock_and_records_it(): void
    {
        Carbon::setTestNow('2026-10-23 12:00:00');
        [$order, $products] = $this->paidOrder(OrderStatus::Processing);

        $this->changeStatus($order, OrderStatus::Cancelled)->assertSessionHasNoErrors();

        $this->assertSame(OrderStatus::Cancelled, $order->refresh()->status);
        $this->assertSame([12, 13], $products->map(fn (Product $product) => $product->inventory->refresh()->quantity)->all());

        $movements = InventoryMovement::orderBy('product_id')->get();
        $this->assertSame([2, 3], $movements->pluck('change')->all());
        $this->assertTrue($movements->every(fn (InventoryMovement $movement) => $movement->reason === InventoryMovementReason::Cancellation
            && $movement->order_id === $order->id
            && $movement->user_id === $this->admin->id));

        $this->actingAs($this->admin)->get("/orders/{$order->id}")->assertSee('Cancellation')->assertSee('+3');
    }

    public function test_cancelling_twice_never_returns_the_stock_twice(): void
    {
        [$order, $products] = $this->paidOrder();

        $this->changeStatus($order, OrderStatus::Cancelled)->assertSessionHasNoErrors();
        $this->changeStatus($order, OrderStatus::Cancelled)->assertSessionHasErrors('status');

        $this->assertSame(12, $products[0]->inventory->refresh()->quantity);
        $this->assertSame(2, InventoryMovement::count());
    }

    public function test_an_unknown_status_is_rejected(): void
    {
        [$order] = $this->paidOrder();

        $this->changeStatus($order, 'refunded')->assertSessionHasErrors('status');
    }
}
