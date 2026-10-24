<?php

namespace Tests\Feature\Products;

use App\Models\User;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\Feature\FeatureTestCase;

/** The panel asks the api-gateway's GraphQL API what customers find (Nest is faked here). */
class ShopSearchTest extends FeatureTestCase
{
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.nest.url' => 'http://nest.test:3000']);
        $this->admin = User::factory()->admin()->create();
    }

    private function fakeShop(array $products, string $elasticsearch = 'up'): void
    {
        Http::fake([
            'nest.test:3000/api/graphql' => Http::response(['data' => ['products' => ['data' => $products, 'meta' => ['total' => count($products)]]]]),
            'nest.test:3000/api/health' => Http::response(['status' => 'ok', 'checks' => ['database' => 'up', 'redis' => 'up', 'elasticsearch' => $elasticsearch]]),
        ]);
    }

    private function headphones(): array
    {
        return ['id' => 7, 'name' => 'Wireless Headphones', 'sku' => 'AUD-HP-01', 'price' => '129.90', 'stock' => 40, 'imagePath' => null, 'category' => ['name' => 'Audio']];
    }

    public function test_the_page_asks_for_a_search_term_first(): void
    {
        Http::fake();

        $this->actingAs($this->admin)->get('/shop-search')->assertOk()->assertSee('Type what a customer would type');
        Http::assertNothingSent();
    }

    public function test_it_shows_what_customers_find_through_graphql(): void
    {
        $this->fakeShop([$this->headphones()]);

        $response = $this->actingAs($this->admin)->get('/shop-search?q=headphnes&sort=relevance')
            ->assertOk()
            ->assertSee('Wireless Headphones')
            ->assertSee('AUD-HP-01')
            ->assertSee(route('products.edit', 7))
            ->assertDontSee('plain search');

        Http::assertSent(fn (Request $request) => $request->url() === 'http://nest.test:3000/api/graphql'
            && str_contains($request['query'], 'products(search: $search')
            && $request['variables']['search'] === 'headphnes'
            && $request['variables']['sortBy'] === 'Relevance'
            && $request->header('X-Request-Id') === [$response->headers->get('X-Request-Id')]);
    }

    public function test_it_says_when_the_shop_searches_without_elasticsearch(): void
    {
        $this->fakeShop([], elasticsearch: 'down');

        $this->actingAs($this->admin)->get('/shop-search?q=headphnes')
            ->assertOk()
            ->assertSee('Elasticsearch is down, so the shop is using its plain search')
            ->assertSee('Customers would find nothing for this search.');
    }

    public function test_an_unreachable_or_failing_shop_api_is_shown_not_thrown(): void
    {
        Http::fake(['nest.test:3000/*' => fn () => throw new ConnectionException('Connection refused')]);
        $this->actingAs($this->admin)->get('/shop-search?q=mug')->assertOk()->assertSee('The shop API is not reachable right now');

        Http::fake(['nest.test:3000/*' => Http::response(['errors' => [['message' => 'Bad Request']], 'data' => null])]);
        $this->actingAs($this->admin)->get('/shop-search?q=mug')->assertOk()->assertSee('The shop API is not reachable right now');
    }

    public function test_the_filters_are_validated(): void
    {
        Http::fake();

        $this->actingAs($this->admin)->get('/shop-search?q=mug&min_price=50&max_price=10&sort=popularity')
            ->assertSessionHasErrors(['max_price', 'sort']);
        Http::assertNothingSent();
    }
}
