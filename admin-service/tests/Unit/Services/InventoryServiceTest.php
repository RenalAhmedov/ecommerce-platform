<?php

namespace Tests\Unit\Services;

use App\Enums\InventoryMovementReason;
use App\Models\User;
use App\Repositories\InventoryRepository;
use App\Services\InventoryService;
use Tests\TestCase;

class InventoryServiceTest extends TestCase
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

    public function test_zero_stock_creates_the_row_but_no_movement(): void
    {
        $repository = $this->mock(InventoryRepository::class);
        $repository->shouldReceive('create')->once()->with(3, 0);
        $repository->shouldNotReceive('recordMovement');

        app(InventoryService::class)->initializeStock(3, 0, User::factory()->make());
    }
}
