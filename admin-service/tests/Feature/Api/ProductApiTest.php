<?php

namespace Tests\Feature\Api;

use App\Enums\OrderStatus;
use App\Jobs\ProcessProductImage;
use App\Models\Category;
use App\Models\Inventory;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use App\Repositories\RevokedTokenRepository;
use Firebase\JWT\JWT;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Mockery\MockInterface;
use RedisException;
use Tests\Feature\FeatureTestCase;

class ProductApiTest extends FeatureTestCase
{
    private User $admin;

    private RevokedTokenRepository&MockInterface $revokedTokens;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();
        $this->revokedTokens = $this->mock(RevokedTokenRepository::class);
        $this->revokedTokens->shouldReceive('isRevoked')->andReturnFalse()->byDefault();
    }

    /** Builds a token exactly like the Nest api-gateway does. */
    private function tokenFor(User $user, array $overrides = [], ?string $secret = null): string
    {
        $claims = [
            'sub' => $user->id,
            'email' => $user->email,
            'role' => $user->role->value,
            'jti' => (string) Str::uuid(),
            'iat' => time(),
            'exp' => time() + 3600,
            ...$overrides,
        ];

        return JWT::encode(array_filter($claims, fn ($value) => $value !== null), $secret ?? config('auth.jwt.secret'), 'HS256');
    }

    private function api(?string $token = null): static
    {
        return $this->withToken($token ?? $this->tokenFor($this->admin));
    }

    public function test_requests_without_a_token_get_401(): void
    {
        $this->getJson('/admin/api/products')->assertUnauthorized();
    }

    public function test_invalid_tokens_get_401(): void
    {
        $tokens = [
            'wrong secret' => $this->tokenFor($this->admin, secret: 'another-secret-another-secret-12345'),
            'expired' => $this->tokenFor($this->admin, ['iat' => time() - 7200, 'exp' => time() - 3600]),
            'no jti' => $this->tokenFor($this->admin, ['jti' => null]),
            'garbage' => 'not.a.jwt',
            'deleted user' => $this->tokenFor(tap(User::factory()->admin()->create())->delete()),
        ];

        foreach ($tokens as $case => $token) {
            $this->api($token)->getJson('/admin/api/products')->assertUnauthorized();
        }
    }

    public function test_a_token_revoked_by_a_nest_logout_gets_401(): void
    {
        $token = $this->tokenFor($this->admin, ['jti' => 'revoked-jti']);
        $this->revokedTokens->shouldReceive('isRevoked')->with('revoked-jti')->andReturnTrue();

        $this->api($token)->getJson('/admin/api/products')->assertUnauthorized();
    }

    public function test_the_revocation_check_fails_open_when_redis_is_down(): void
    {
        $this->revokedTokens->shouldReceive('isRevoked')->andThrow(new RedisException('Connection refused'));

        $this->api()->getJson('/admin/api/products')->assertOk();
    }

    public function test_customer_tokens_get_403(): void
    {
        $customer = User::factory()->create();

        $this->api($this->tokenFor($customer))->getJson('/admin/api/products')->assertForbidden();
    }

    public function test_the_role_comes_from_the_database_not_the_token(): void
    {
        $customer = User::factory()->create();

        $this->api($this->tokenFor($customer, ['role' => 'admin']))->getJson('/admin/api/products')->assertForbidden();
    }

    public function test_the_list_uses_the_nest_response_shape(): void
    {
        $category = Category::factory()->create(['name' => 'Mugs']);
        $product = Product::factory()->for($category)->create(['sku' => 'MUG-1', 'price' => 9.5]);
        Inventory::factory()->for($product)->create(['quantity' => 4]);
        Product::factory()->count(2)->create();

        $page = $this->api()->getJson('/admin/api/products?limit=2&page=1')->assertOk();

        $this->assertSame(['data', 'meta'], array_keys($page->json()));
        $this->assertSame(['page' => 1, 'limit' => 2, 'total' => 3, 'totalPages' => 2], $page->json('meta'));
        $page->assertJsonCount(2, 'data');

        $item = $this->api()->getJson('/admin/api/products?search=MUG-1')->json('data.0');

        $this->assertSame(
            ['id', 'name', 'slug', 'sku', 'description', 'price', 'imagePath', 'imageUrl', 'thumbnailUrl', 'isActive', 'category', 'stock', 'createdAt', 'updatedAt', 'deletedAt'],
            array_keys($item),
        );
        $this->assertSame('9.50', $item['price']);
        $this->assertSame(4, $item['stock']);
        $this->assertTrue($item['isActive']);
        $this->assertSame(['id' => $category->id, 'name' => 'Mugs'], $item['category']);
    }

    public function test_show_returns_one_product_and_404_for_trashed(): void
    {
        $product = Product::factory()->create(['sku' => 'SHOW-1']);
        $trashed = tap(Product::factory()->create())->delete();

        $this->api()->getJson("/admin/api/products/{$product->id}")->assertOk()->assertJsonPath('data.sku', 'SHOW-1');
        $this->api()->getJson("/admin/api/products/{$trashed->id}")->assertNotFound();
    }

    public function test_store_creates_a_product_and_returns_201(): void
    {
        $this->api()->postJson('/admin/api/products', [
            'name' => 'Desk Lamp',
            'sku' => 'lamp-1',
            'price' => 49.99,
            'is_active' => true,
            'initial_stock' => 7,
        ])
            ->assertCreated()
            ->assertJsonPath('data.sku', 'LAMP-1')
            ->assertJsonPath('data.slug', 'desk-lamp')
            ->assertJsonPath('data.stock', 7);

        $this->assertSame($this->admin->id, Product::sole()->inventoryMovements()->sole()->user_id);
    }

    public function test_validation_and_conflicts_return_422_json(): void
    {
        Product::factory()->create(['sku' => 'DUP-1']);

        $this->api()->postJson('/admin/api/products', ['name' => 'X', 'sku' => 'bad sku', 'price' => -1])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['sku', 'price']);

        $this->api()->postJson('/admin/api/products', ['name' => 'Dup', 'sku' => 'DUP-1', 'price' => 1])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['sku' => 'A product with this sku already exists.']);
    }

    public function test_update_is_partial(): void
    {
        $product = Product::factory()->create(['name' => 'Keep', 'price' => 1]);

        $this->api()->patchJson("/admin/api/products/{$product->id}", ['price' => 2.5])
            ->assertOk()
            ->assertJsonPath('data.name', 'Keep')
            ->assertJsonPath('data.price', '2.50');
    }

    public function test_image_upload_returns_202_and_the_urls_appear_once_processed(): void
    {
        Storage::fake('public');
        Storage::fake('local');
        $product = Product::factory()->create();

        Queue::fake();
        $this->api()->post("/admin/api/products/{$product->id}/image", ['image' => UploadedFile::fake()->image('a.jpg')], ['Accept' => 'application/json'])
            ->assertAccepted()
            ->assertJsonPath('meta.imageProcessing', true)
            ->assertJsonPath('data.imageUrl', null);
        Queue::assertPushed(ProcessProductImage::class);
    }

    public function test_image_upload_and_removal_through_the_api(): void
    {
        Storage::fake('public');
        Storage::fake('local');
        $product = Product::factory()->create();

        $this->api()->post("/admin/api/products/{$product->id}/image", ['image' => UploadedFile::fake()->image('a.jpg')], ['Accept' => 'application/json'])
            ->assertAccepted();

        $shown = $this->api()->getJson("/admin/api/products/{$product->id}")->json('data');
        $this->assertStringEndsWith($shown['imagePath'], $shown['imageUrl']);
        $this->assertStringEndsWith('-thumb.webp', $shown['thumbnailUrl']);

        $this->api()->deleteJson("/admin/api/products/{$product->id}/image")
            ->assertOk()
            ->assertJsonPath('data.imagePath', null)
            ->assertJsonPath('data.imageUrl', null);
    }

    public function test_orders_list_and_detail_use_the_nest_shape(): void
    {
        $order = Order::factory()->create(['status' => OrderStatus::Paid]);
        OrderItem::factory()->for($order)->create(['product_name' => 'Mug', 'quantity' => 2, 'unit_price' => 4.5, 'line_total' => 9]);

        $this->api()->getJson('/admin/api/orders?status=paid')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.orderNumber', $order->order_number)
            ->assertJsonPath('data.0.itemsCount', 1)
            ->assertJsonMissingPath('data.0.items');

        $this->api()->getJson("/admin/api/orders/{$order->id}")
            ->assertOk()
            ->assertJsonPath('data.allowedStatuses', ['processing', 'cancelled'])
            ->assertJsonPath('data.customer.email', $order->user->email)
            ->assertJsonPath('data.items.0', fn (array $item) => $item['productName'] === 'Mug' && $item['quantity'] === 2 && $item['unitPrice'] === '4.50' && $item['lineTotal'] === '9.00');
    }

    public function test_the_dashboard_figures_are_available_as_json(): void
    {
        $this->api()->getJson('/admin/api/dashboard')
            ->assertOk()
            ->assertJsonStructure(['data' => ['generatedAt', 'revenue' => ['today', 'week', 'month'], 'dailyRevenue', 'ordersByStatus', 'customers', 'lowStock', 'topSellers', 'recentOrders']]);
    }

    public function test_sales_report_as_json(): void
    {
        $this->api()->getJson('/admin/api/reports/sales?period=month&date_from=2026-01-01&date_to=2026-03-31')
            ->assertOk()
            ->assertJsonPath('data.period', 'month')
            ->assertJsonPath('data.from', '2026-01-01')
            ->assertJsonPath('data.to', '2026-03-31')
            ->assertJsonCount(3, 'data.rows')
            ->assertJsonPath('data.totals.revenue', '0.00');

        $this->api()->getJson('/admin/api/reports/sales?period=day&date_from=2020-01-01&date_to=2026-01-01')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('date_to');
    }

    public function test_users_through_the_api(): void
    {
        $this->api()->postJson('/admin/api/users', ['name' => 'Api User', 'email' => 'API@example.test', 'password' => 'secret-password', 'role' => 'customer'])
            ->assertCreated()
            ->assertJsonPath('data.email', 'api@example.test')
            ->assertJsonMissingPath('data.password');

        $user = User::where('email', 'api@example.test')->sole();

        $this->api()->getJson('/admin/api/users?role=customer')->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.ordersCount', 0);
        $this->api()->patchJson("/admin/api/users/{$user->id}", ['role' => 'admin'])->assertOk()->assertJsonPath('data.role', 'admin');
        $this->api()->deleteJson("/admin/api/users/{$this->admin->id}")->assertUnprocessable()->assertJsonValidationErrors('user');
        $this->api()->deleteJson("/admin/api/users/{$user->id}")->assertNoContent();
        $this->api()->patchJson("/admin/api/users/{$user->id}/restore")->assertOk()->assertJsonPath('data.deletedAt', null);
    }

    public function test_order_status_changes_through_the_api(): void
    {
        $order = Order::factory()->create(['status' => OrderStatus::Paid]);

        $this->api()->patchJson("/admin/api/orders/{$order->id}/status", ['status' => 'processing'])
            ->assertOk()
            ->assertJsonPath('data.status', 'processing')
            ->assertJsonPath('data.allowedStatuses', ['shipped', 'cancelled']);

        $this->api()->patchJson("/admin/api/orders/{$order->id}/status", ['status' => 'delivered'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');
    }

    public function test_delete_returns_204_and_restore_brings_it_back(): void
    {
        $product = Product::factory()->create();

        $this->api()->deleteJson("/admin/api/products/{$product->id}")->assertNoContent();
        $this->assertSoftDeleted($product);

        $this->api()->getJson('/admin/api/products?status=trashed')->assertJsonPath('data.0.id', $product->id);

        $this->api()->patchJson("/admin/api/products/{$product->id}/restore")
            ->assertOk()
            ->assertJsonPath('data.deletedAt', null);
        $this->assertNotSoftDeleted($product);
    }
}
