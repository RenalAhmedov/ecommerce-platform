<?php

namespace Tests\Unit\Services;

use App\Models\Product;
use App\Models\User;
use App\Repositories\ProductRepository;
use App\Services\InventoryService;
use App\Services\ProductService;
use Illuminate\Validation\ValidationException;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class ProductServiceTest extends TestCase
{
    private ProductRepository&MockInterface $products;

    private InventoryService&MockInterface $inventory;

    private ProductService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->products = $this->mock(ProductRepository::class);
        $this->inventory = $this->mock(InventoryService::class);
        $this->service = app(ProductService::class);
    }

    public function test_create_locks_checks_then_creates_with_stock(): void
    {
        $admin = User::factory()->make(['id' => 7]);
        $product = Product::factory()->make(['id' => 42]);

        $this->products->shouldReceive('lockUniqueValues')->once()->with('MUG-1', 'mug')->ordered();
        $this->products->shouldReceive('existsBySku')->once()->with('MUG-1', null)->andReturnFalse()->ordered();
        $this->products->shouldReceive('existsBySlug')->once()->with('mug', null)->andReturnFalse()->ordered();
        $this->products->shouldReceive('create')->once()
            ->with(['name' => 'Mug', 'sku' => 'MUG-1', 'slug' => 'mug', 'price' => '5'])
            ->andReturn($product)->ordered();
        $this->inventory->shouldReceive('initializeStock')->once()->with(42, 10, $admin);

        $result = $this->service->create(
            ['name' => 'Mug', 'sku' => 'MUG-1', 'slug' => 'mug', 'price' => '5', 'initial_stock' => 10],
            $admin,
        );

        $this->assertSame($product, $result);
    }

    public function test_create_reports_every_taken_value_and_creates_nothing(): void
    {
        $this->products->shouldReceive('lockUniqueValues');
        $this->products->shouldReceive('existsBySku')->andReturnTrue();
        $this->products->shouldReceive('existsBySlug')->andReturnTrue();
        $this->products->shouldNotReceive('create');
        $this->inventory->shouldNotReceive('initializeStock');

        try {
            $this->service->create(['name' => 'Mug', 'sku' => 'MUG-1', 'slug' => 'mug', 'price' => '5'], User::factory()->make());
            $this->fail('Expected a ValidationException.');
        } catch (ValidationException $e) {
            $this->assertSame(['sku', 'slug'], array_keys($e->errors()));
        }
    }

    public function test_update_only_locks_and_checks_values_that_changed(): void
    {
        $stored = Product::factory()->make(['id' => 5, 'sku' => 'OLD-1', 'slug' => 'old']);
        $stored->syncOriginal();

        $this->products->shouldReceive('lockById')->once()->with(5)->andReturn($stored);
        $this->products->shouldReceive('lockUniqueValues')->once()->with('NEW-1', null);
        $this->products->shouldReceive('existsBySku')->once()->with('NEW-1', 5)->andReturnFalse();
        $this->products->shouldNotReceive('existsBySlug');
        $this->products->shouldReceive('save')->once()->with($stored);

        $result = $this->service->update($stored, ['sku' => 'NEW-1', 'slug' => 'old']);

        $this->assertSame('NEW-1', $result->sku);
    }

    public function test_starting_stock_defaults_to_zero(): void
    {
        $this->products->shouldReceive('lockUniqueValues', 'existsBySku', 'existsBySlug')->andReturnFalse();
        $this->products->shouldReceive('create')->andReturn(Product::factory()->make(['id' => 1]));
        $this->inventory->shouldReceive('initializeStock')->once()->with(1, 0, Mockery::type(User::class));

        $this->service->create(['name' => 'Mug', 'sku' => 'MUG-1', 'slug' => 'mug', 'price' => '5'], User::factory()->make());
    }
}
