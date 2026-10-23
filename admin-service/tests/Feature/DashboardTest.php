<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Models\Inventory;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use App\Services\DashboardService;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        // Wednesday: the week started on Monday the 19th, the month on the 1st.
        Carbon::setTestNow('2026-10-21 12:00:00');
        $this->admin = User::factory()->admin()->create(['created_at' => '2026-09-01']);
    }

    private function order(OrderStatus $status, string $total, ?string $paidAt): Order
    {
        return Order::factory()->create(['status' => $status, 'total' => $total, 'subtotal' => $total, 'paid_at' => $paidAt, 'created_at' => $paidAt ?? now()]);
    }

    private function metrics(): array
    {
        return app(DashboardService::class)->metrics();
    }

    public function test_revenue_counts_only_kept_money_per_period(): void
    {
        $this->order(OrderStatus::Paid, '100.00', '2026-10-21 08:00:00');
        $this->order(OrderStatus::Processing, '50.00', '2026-10-20 23:59:59');
        $this->order(OrderStatus::Delivered, '30.00', '2026-10-05 10:00:00');
        $this->order(OrderStatus::Paid, '20.00', '2026-09-30 23:59:59');
        $this->order(OrderStatus::Cancelled, '999.00', '2026-10-21 09:00:00');
        $this->order(OrderStatus::Pending, '77.00', null);

        $revenue = $this->metrics()['revenue'];

        $this->assertSame(['orders' => 1, 'revenue' => '100.00', 'average' => '100.00'], $revenue['today']);
        $this->assertSame(['orders' => 2, 'revenue' => '150.00', 'average' => '75.00'], $revenue['week']);
        $this->assertSame(['orders' => 3, 'revenue' => '180.00', 'average' => '60.00'], $revenue['month']);
    }

    public function test_the_chart_has_one_entry_per_day_including_quiet_days(): void
    {
        $this->order(OrderStatus::Paid, '100.00', '2026-10-21 08:00:00');
        $this->order(OrderStatus::Shipped, '50.00', '2026-10-20 10:00:00');
        $this->order(OrderStatus::Shipped, '25.50', '2026-10-20 18:00:00');

        $days = $this->metrics()['dailyRevenue'];

        $this->assertCount(14, $days);
        $this->assertSame(['day' => '2026-10-08', 'revenue' => '0.00'], $days[0]);
        $this->assertSame(['day' => '2026-10-20', 'revenue' => '75.50'], $days[12]);
        $this->assertSame(['day' => '2026-10-21', 'revenue' => '100.00'], $days[13]);
    }

    public function test_orders_by_status_lists_every_status(): void
    {
        $this->order(OrderStatus::Paid, '1', now()->toDateTimeString());
        $this->order(OrderStatus::Paid, '1', now()->toDateTimeString());
        $this->order(OrderStatus::Pending, '1', null);

        $counts = $this->metrics()['ordersByStatus'];

        $this->assertSame(array_map(fn ($status) => $status->value, OrderStatus::cases()), array_keys($counts));
        $this->assertSame([2, 1, 0], [$counts['paid'], $counts['pending'], $counts['shipped']]);
    }

    public function test_customer_counts_leave_out_admins(): void
    {
        User::factory()->create(['created_at' => '2026-10-02']);
        User::factory()->create(['created_at' => '2026-09-15']);

        $this->assertSame(['total' => 2, 'newSince' => 1], $this->metrics()['customers']);
    }

    public function test_low_stock_lists_active_products_at_or_below_their_threshold(): void
    {
        $stock = function (string $sku, int $quantity, array $state = []) {
            $product = Product::factory()->create(['sku' => $sku, ...$state]);
            Inventory::factory()->for($product)->create(['quantity' => $quantity, 'low_stock_threshold' => 5]);

            return $product;
        };
        $stock('EMPTY-1', 0);
        $stock('LOW-1', 5);
        $stock('FINE-1', 6);
        $stock('OFF-1', 0, ['is_active' => false]);
        $stock('GONE-1', 0)->delete();

        $this->assertSame(['EMPTY-1', 'LOW-1'], array_column($this->metrics()['lowStock'], 'sku'));
    }

    public function test_top_sellers_rank_by_units_sold_in_kept_orders(): void
    {
        [$mug, $lamp] = Product::factory()->count(2)->sequence(['sku' => 'MUG-1'], ['sku' => 'LAMP-1'])->create();
        $kept = $this->order(OrderStatus::Delivered, '1', '2026-10-15 10:00:00');
        $cancelled = $this->order(OrderStatus::Cancelled, '1', '2026-10-15 10:00:00');
        OrderItem::factory()->for($kept)->for($mug)->create(['quantity' => 3, 'line_total' => '30.00']);
        OrderItem::factory()->for($kept)->for($lamp)->create(['quantity' => 1, 'line_total' => '40.00']);
        OrderItem::factory()->for($cancelled)->for($lamp)->create(['quantity' => 10, 'line_total' => '400.00']);

        $top = $this->metrics()['topSellers'];

        $this->assertSame(['MUG-1', 'LAMP-1'], array_column($top, 'sku'));
        $this->assertSame([3, '30.00'], [$top[0]['units'], $top[0]['revenue']]);
    }

    public function test_figures_are_cached_then_refreshed(): void
    {
        $this->order(OrderStatus::Paid, '10.00', now()->toDateTimeString());
        $this->assertSame('10.00', $this->metrics()['revenue']['today']['revenue']);

        $this->order(OrderStatus::Paid, '5.00', now()->toDateTimeString());
        $this->travel(DashboardService::FRESH_SECONDS - 1)->seconds();
        $this->assertSame('10.00', $this->metrics()['revenue']['today']['revenue'], 'served from the cache');

        $this->travel(DashboardService::STALE_SECONDS + 1)->seconds();
        $this->assertSame('15.00', $this->metrics()['revenue']['today']['revenue'], 'recomputed once the cache expired');
    }

    public function test_the_dashboard_page_and_api_show_the_figures(): void
    {
        $order = $this->order(OrderStatus::Paid, '42.00', now()->toDateTimeString());

        $this->actingAs($this->admin)->get('/dashboard')
            ->assertOk()
            ->assertSee('42.00')
            ->assertSee($order->order_number)
            ->assertSee('To process');

        $this->actingAs($this->admin)->get('/admin/api/dashboard')->assertUnauthorized();
    }
}
