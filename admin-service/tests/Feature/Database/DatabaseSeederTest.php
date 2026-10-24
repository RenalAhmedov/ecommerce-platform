<?php

namespace Tests\Feature\Database;

use App\Enums\OrderStatus;
use App\Models\Category;
use App\Models\Inventory;
use App\Models\InventoryMovement;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\FeatureTestCase;

class DatabaseSeederTest extends FeatureTestCase
{
    /** Picture processing is slow, so only the picture test lets the queued jobs run. */
    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Storage::fake('public');
    }

    public function test_seeding_twice_creates_the_demo_data_once(): void
    {
        Queue::fake();
        $this->seed(DatabaseSeeder::class);
        $counts = [User::count(), Category::count(), Product::count(), Order::count(), InventoryMovement::count()];

        $this->seed(DatabaseSeeder::class);

        $this->assertSame([9, 10, 20, 24], array_slice($counts, 0, 4));
        $this->assertSame($counts, [User::count(), Category::count(), Product::count(), Order::count(), InventoryMovement::count()]);
    }

    public function test_every_stock_level_matches_its_ledger(): void
    {
        Queue::fake();
        $this->seed(DatabaseSeeder::class);

        foreach (Inventory::all() as $inventory) {
            $this->assertSame(
                (int) InventoryMovement::where('product_id', $inventory->product_id)->sum('change'),
                $inventory->quantity,
                "Stock of product {$inventory->product_id} does not match its movements.",
            );
        }
        $this->assertSame(0, Inventory::whereRelation('product', 'sku', 'ACC-STD-01')->value('quantity'));
    }

    public function test_orders_cover_every_status_and_only_declined_ones_are_unpaid(): void
    {
        Queue::fake();
        $this->seed(DatabaseSeeder::class);

        $statuses = Order::distinct()->pluck('status')->map->value->sort()->values()->all();

        $this->assertSame(['cancelled', 'delivered', 'paid', 'payment_failed', 'processing', 'shipped'], $statuses);
        $this->assertSame(0, Order::where('status', OrderStatus::PaymentFailed)->whereNotNull('paid_at')->count());
        $this->assertSame(0, Order::where('status', '!=', OrderStatus::PaymentFailed)->whereNull('paid_at')->count());
        $this->assertSame(0, Order::where('created_at', '>', now())->count());
    }

    public function test_the_demo_admin_can_log_in_and_products_get_their_pictures(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->post('/login', ['email' => UserSeeder::ADMIN_EMAIL, 'password' => UserSeeder::PASSWORD])
            ->assertRedirect(route('dashboard', absolute: false));
        $this->assertSame(20, Product::whereNotNull('image_path')->count());
    }
}
