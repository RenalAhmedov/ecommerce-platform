<?php

namespace Tests\Unit\Services;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\User;
use App\Repositories\OrderRepository;
use App\Services\InventoryService;
use App\Services\OrderService;
use Illuminate\Validation\ValidationException;
use Mockery\MockInterface;
use Tests\Unit\UnitTestCase;

class OrderServiceTest extends UnitTestCase
{
    private OrderRepository&MockInterface $orders;

    private InventoryService&MockInterface $inventory;

    private OrderService $service;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->orders = $this->mock(OrderRepository::class);
        $this->inventory = $this->mock(InventoryService::class);
        $this->service = app(OrderService::class);
        $this->admin = User::factory()->admin()->make(['id' => 1]);
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

        $result = $this->service->changeStatus($this->order(OrderStatus::Paid), OrderStatus::Processing, $this->admin);

        $this->assertSame(OrderStatus::Processing, $result->status);
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
    }

    public function test_cancelling_returns_the_ordered_quantities_to_stock(): void
    {
        $locked = $this->order(OrderStatus::Processing);
        $this->orders->shouldReceive('lockById')->andReturn($locked);
        $this->orders->shouldReceive('quantitiesByProduct')->with($locked)->andReturn([5 => 2, 3 => 1]);
        $this->inventory->shouldReceive('release')->once()->with([5 => 2, 3 => 1], 10, $this->admin)->ordered();
        $this->orders->shouldReceive('save')->once()->ordered();

        $this->assertSame(OrderStatus::Cancelled, $this->service->changeStatus($locked, OrderStatus::Cancelled, $this->admin)->status);
    }

    public function test_pending_orders_cannot_be_changed_by_an_admin(): void
    {
        $this->orders->shouldReceive('lockById')->andReturn($this->order(OrderStatus::Pending));
        $this->orders->shouldNotReceive('save');

        $this->expectException(ValidationException::class);

        $this->service->changeStatus($this->order(OrderStatus::Pending), OrderStatus::Cancelled, $this->admin);
    }
}
