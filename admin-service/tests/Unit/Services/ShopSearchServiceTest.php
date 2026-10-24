<?php

namespace Tests\Unit\Services;

use App\Integrations\NestShopClient;
use App\Integrations\NestUnavailableException;
use App\Services\ShopSearchService;
use Illuminate\Support\Facades\Log;
use Mockery\MockInterface;
use Tests\Unit\UnitTestCase;

class ShopSearchServiceTest extends UnitTestCase
{
    private NestShopClient&MockInterface $shop;

    protected function setUp(): void
    {
        parent::setUp();

        $this->shop = $this->mock(NestShopClient::class);
    }

    private function filters(array $overrides = []): array
    {
        return ['q' => 'headphnes', 'sort' => 'relevance', 'order' => 'desc', 'category_id' => null, 'min_price' => null, 'max_price' => null, ...$overrides];
    }

    private function page(array $products = [], int $total = 0): array
    {
        return ['products' => ['data' => $products, 'meta' => ['total' => $total]]];
    }

    public function test_nothing_is_asked_without_a_search_term(): void
    {
        $this->shop->shouldNotReceive('graphql');

        $this->assertNull(app(ShopSearchService::class)->search($this->filters(['q' => null])));
    }

    public function test_the_filters_become_graphql_variables(): void
    {
        $this->shop->shouldReceive('graphql')->once()
            ->withArgs(fn (string $query, array $variables) => str_contains($query, 'products(search: $search')
                && $variables === ['search' => 'lamp', 'sortBy' => 'Price', 'sortOrder' => 'Asc', 'categoryId' => 3, 'minPrice' => 5.0, 'maxPrice' => 49.9])
            ->andReturn($this->page());
        $this->shop->shouldReceive('health')->andReturn(['status' => 'ok', 'checks' => ['elasticsearch' => 'up']]);

        app(ShopSearchService::class)->search($this->filters(['q' => 'lamp', 'sort' => 'price', 'order' => 'asc', 'category_id' => 3, 'min_price' => '5', 'max_price' => '49.90']));
    }

    public function test_results_are_shaped_for_the_page(): void
    {
        $this->shop->shouldReceive('graphql')->andReturn($this->page([
            ['id' => 7, 'name' => 'Wireless Headphones', 'sku' => 'AUD-HP-01', 'price' => '129.90', 'stock' => 40, 'imagePath' => 'products/7/a.webp', 'category' => ['name' => 'Audio']],
        ], total: 1));
        $this->shop->shouldReceive('health')->andReturn(['status' => 'ok', 'checks' => ['elasticsearch' => 'up']]);

        $results = app(ShopSearchService::class)->search($this->filters());

        $this->assertTrue($results['available']);
        $this->assertSame(['total' => 1, 'searchEngine' => 'up'], ['total' => $results['total'], 'searchEngine' => $results['searchEngine']]);
        $this->assertSame('Audio', $results['products'][0]['category']);
        $this->assertStringEndsWith('/storage/products/7/a-thumb.webp', $results['products'][0]['thumbnailUrl']);
    }

    public function test_an_unreachable_shop_is_reported_not_thrown(): void
    {
        $this->shop->shouldReceive('graphql')->andThrow(new NestUnavailableException('Connection refused'));
        Log::shouldReceive('warning')->once();

        $this->assertSame(
            ['available' => false, 'products' => [], 'total' => 0, 'searchEngine' => 'unknown'],
            app(ShopSearchService::class)->search($this->filters()),
        );
    }

    public function test_the_search_engine_state_is_unknown_when_health_fails(): void
    {
        $this->shop->shouldReceive('graphql')->andReturn($this->page());
        $this->shop->shouldReceive('health')->andThrow(new NestUnavailableException('timeout'));

        $this->assertSame('unknown', app(ShopSearchService::class)->search($this->filters())['searchEngine']);
    }
}
