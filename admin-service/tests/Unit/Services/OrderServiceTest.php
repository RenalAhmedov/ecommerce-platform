<?php

namespace Tests\Unit\Services;

use App\Enums\OrderStatus;
use App\Events\OrderPaid;
use App\Events\OrderStatusChanged;
use App\Models\Order;
use App\Models\User;
use App\Repositories\OrderRepository;
use App\Services\CatalogNotifier;
use App\Services\InventoryService;
use App\Services\OrderService;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Mockery\MockInterface;
use Tests\Unit\UnitTestCase;

class OrderServiceTest extends UnitTestCase
{
    private CatalogNotifier&MockInterface $catalog;

    private OrderRepository&MockInterface $orders;

    private InventoryService&MockInterface $inventory;

    private OrderService $service;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->orders = $this->mock(OrderRepository::class);
        $this->inventory = $this->mock(InventoryService::class);
        $this->catalog = $this->mock(CatalogNotifier::class);
        $this->catalog->shouldReceive('catalogChanged')->byDefault();
        $this->service = app(OrderService::class);
        $this->admin = User::factory()->admin()->make(['id' => 1, 'name' => 'Shop Admin']);
        Event::fake([OrderStatusChanged::class, OrderPaid::class]);
    }

    private function order(OrderStatus $status): Order
    {
        $order = new Order(['order_number' => 'ORD-1', 'status' => $status, 'user_id' => 9]);
        $order->id = 10;
        $order->syncOriginal();

        return $order;
    }

    public function test_an_allowed_step_is_saved_without_touching_stock(): void
    {
        $locked = $this->order(OrderStatus::Paid);
        $this->orders->shouldReceive('lockById')->once()->with(10)->andReturn($locked);
        $this->orders->shouldReceive('save')->once()->with($locked);
        $this->inventory->shouldNotReceive('release');
        $this->catalog->shouldNotReceive('catalogChanged');

        $result = $this->service->changeStatus($this->order(OrderStatus::Paid), OrderStatus::Processing, $this->admin);

        $this->assertSame(OrderStatus::Processing, $result->status);
        Event::assertDispatched(OrderStatusChanged::class, fn (OrderStatusChanged $event) => $event->orderId === 10
            && $event->status === OrderStatus::Processing
            && $event->changedBy === 'Shop Admin');
    }

    public function test_the_status_is_checked_on_the_locked_row_not_the_stale_one(): void
    {
        // The page showed "paid", but another admin cancelled the order in the meantime.
        $this->orders->shouldReceive('lockById')->andReturn($this->order(OrderStatus::Cancelled));
        $this->orders->shouldNotReceive('save');
        $this->inventory->shouldNotReceive('release');

        try {
            $this->service->changeStatus($this->order(OrderStatus::Paid), OrderStatus::Cancelled, $this->admin);
            $this->fail('A cancelled order was cancelled again.');
        } catch (ValidationException $e) {
            $this->assertSame(['status' => ['Order ORD-1 is Cancelled and cannot be changed to Cancelled.']], $e->errors());
        }
        Event::assertNotDispatched(OrderStatusChanged::class);
    }

    public function test_cancelling_returns_the_ordered_quantities_to_stock(): void
    {
        $locked = $this->order(OrderStatus::Processing);
        $this->orders->shouldReceive('lockById')->andReturn($locked);
        $this->orders->shouldReceive('quantitiesByProduct')->with($locked)->andReturn([5 => 2, 3 => 1]);
        $this->inventory->shouldReceive('release')->once()->with([5 => 2, 3 => 1], 10, $this->admin)->ordered();
        $this->orders->shouldReceive('save')->once()->ordered();
        $this->catalog->shouldReceive('catalogChanged')->once()->with('order cancelled, stock returned');

        $this->assertSame(OrderStatus::Cancelled, $this->service->changeStatus($locked, OrderStatus::Cancelled, $this->admin)->status);
    }

    public function test_pending_orders_cannot_be_changed_by_an_admin(): void
    {
        $this->orders->shouldReceive('lockById')->andReturn($this->order(OrderStatus::Pending));
        $this->orders->shouldNotReceive('save');

        $this->expectException(ValidationException::class);

        $this->service->changeStatus($this->order(OrderStatus::Pending), OrderStatus::Cancelled, $this->admin);
    }

    public function test_a_paid_shop_order_is_announced_to_the_admins(): void
    {
        $order = $this->order(OrderStatus::Paid)->forceFill(['total' => '45.00']);
        $order->setRelation('user', User::factory()->make(['name' => 'Ada Lovelace']));
        $this->orders->shouldReceive('findWithCustomer')->once()->with(10)->andReturn($order);

        $this->service->announcePaid(10);

        Event::assertDispatched(OrderPaid::class, fn (OrderPaid $event) => $event->broadcastWith() === [
            'id' => 10,
            'orderNumber' => 'ORD-1',
            'total' => '45.00',
            'customer' => 'Ada Lovelace',
            'url' => '/orders/10',
        ]);
    }

    public function test_an_announcement_for_an_unknown_order_is_only_logged(): void
    {
        $this->orders->shouldReceive('findWithCustomer')->andReturnNull();
        Log::shouldReceive('warning')->once()->with('Paid order announced by the api-gateway does not exist', ['order_id' => 404]);

        $this->service->announcePaid(404);

        Event::assertNotDispatched(OrderPaid::class);
    }
}
