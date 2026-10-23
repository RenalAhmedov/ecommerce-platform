<?php

namespace Tests\Unit\Services;

use App\Enums\InventoryMovementReason;
use App\Models\User;
use App\Repositories\InventoryRepository;
use App\Services\InventoryService;
use Tests\Unit\UnitTestCase;

class InventoryServiceTest extends UnitTestCase
{
    public function test_opening_stock_creates_the_row_and_an_initial_movement(): void
    {
        $repository = $this->mock(InventoryRepository::class);
        $repository->shouldReceive('create')->once()->with(3, 12);
        $repository->shouldReceive('recordMovement')->once()->with([
            'product_id' => 3,
            'user_id' => 9,
            'change' => 12,
            'reason' => InventoryMovementReason::Initial,
        ]);

        app(InventoryService::class)->initializeStock(3, 12, User::factory()->make(['id' => 9]));
    }

    public function test_release_locks_rows_in_product_order_and_records_each_return(): void
    {
        $admin = User::factory()->make(['id' => 9]);
        $repository = $this->mock(InventoryRepository::class);
        $repository->shouldReceive('lockByProductIds')->once()->with([3, 5])->ordered();
        $repository->shouldReceive('adjustQuantity')->once()->with(3, 1)->ordered();
        $repository->shouldReceive('recordMovement')->once()->with(['product_id' => 3, 'order_id' => 10, 'user_id' => 9, 'change' => 1, 'reason' => InventoryMovementReason::Cancellation])->ordered();
        $repository->shouldReceive('adjustQuantity')->once()->with(5, 2)->ordered();
        $repository->shouldReceive('recordMovement')->once()->with(['product_id' => 5, 'order_id' => 10, 'user_id' => 9, 'change' => 2, 'reason' => InventoryMovementReason::Cancellation])->ordered();

        app(InventoryService::class)->release([5 => 2, 3 => 1], 10, $admin);
    }

    public function test_releasing_nothing_takes_no_locks(): void
    {
        $repository = $this->mock(InventoryRepository::class);
        $repository->shouldNotReceive('lockByProductIds');

        app(InventoryService::class)->release([], 10, null);
    }

    public function test_zero_stock_creates_the_row_but_no_movement(): void
    {
        $repository = $this->mock(InventoryRepository::class);
        $repository->shouldReceive('create')->once()->with(3, 0);
        $repository->shouldNotReceive('recordMovement');

        app(InventoryService::class)->initializeStock(3, 0, User::factory()->make());
    }
}
