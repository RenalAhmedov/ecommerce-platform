<?php

namespace Tests\Feature\Integrations;

use App\Enums\OrderStatus;
use App\Jobs\NotifyCatalogChanged;
use App\Models\Category;
use App\Models\Inventory;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\Feature\FeatureTestCase;

/** Catalogue changes made in the panel reach the Nest api-gateway's webhook. */
class CatalogWebhookTest extends FeatureTestCase
{
    private const KEY = 'feature-test-internal-api-key-0123456789abcdef';

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.nest.url' => 'http://nest.test:3000', 'services.nest.internal_api_key' => self::KEY]);
        Http::fake(['nest.test:3000/*' => Http::response(['status' => 'processed'], 202)]);
        $this->admin = User::factory()->admin()->create();
    }

    private function assertWebhookSent(int $times = 1): void
    {
        Http::assertSentCount($times);
        Http::assertSent(fn (Request $request) => $request->url() === 'http://nest.test:3000/api/internal/webhooks/catalog'
            && $request->header('X-Api-Key') === [self::KEY]
            && $request['type'] === 'catalog.changed');
    }

    public function test_creating_a_product_in_the_panel_notifies_the_gateway_with_the_same_request_id(): void
    {
        $response = $this->actingAs($this->admin)->post('/products', ['name' => 'Desk Lamp', 'sku' => 'LAMP-1', 'price' => '49.90'])->assertSessionHasNoErrors();

        $this->assertWebhookSent();
        Http::assertSent(fn (Request $request) => $request->header('X-Request-Id') === [$response->headers->get('X-Request-Id')]);
    }

    public function test_category_changes_and_cancelled_orders_notify_the_gateway(): void
    {
        $category = Category::factory()->create();
        $this->actingAs($this->admin)->put("/categories/{$category->id}", ['name' => 'Renamed'])->assertSessionHasNoErrors();

        $order = Order::factory()->create(['status' => OrderStatus::Paid]);
        $product = Product::factory()->create();
        Inventory::factory()->for($product)->create(['quantity' => 1]);
        OrderItem::factory()->for($order)->for($product)->create(['quantity' => 1]);
        $this->actingAs($this->admin)->patch("/orders/{$order->id}/status", ['status' => 'cancelled'])->assertSessionHasNoErrors();

        $this->assertWebhookSent(times: 2);
    }

    public function test_changes_that_do_not_touch_the_catalogue_send_nothing(): void
    {
        $order = Order::factory()->create(['status' => OrderStatus::Paid]);

        $this->actingAs($this->admin)->patch("/orders/{$order->id}/status", ['status' => 'processing']);
        $this->actingAs($this->admin)->post('/users', ['name' => 'X', 'email' => 'x@example.test', 'password' => 'secret-password', 'role' => 'customer']);

        Http::assertNothingSent();
    }

    public function test_a_burst_of_changes_queues_a_single_notification(): void
    {
        config(['queue.default' => 'redis']);
        Queue::connection('redis')->clear('default');

        foreach (['MUG-1', 'MUG-2', 'MUG-3'] as $sku) {
            $this->actingAs($this->admin)->post('/products', ['name' => $sku, 'sku' => $sku, 'price' => '1'])->assertSessionHasNoErrors();
        }

        $this->assertSame(1, Queue::connection('redis')->size('default'));
        $this->assertSame(NotifyCatalogChanged::class, Queue::connection('redis')->pop('default')?->resolveName());
        Http::assertNothingSent();
    }
}
