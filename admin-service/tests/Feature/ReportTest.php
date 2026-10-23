<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\ReportPeriod;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use App\Reports\SalesReport;
use App\Services\ReportService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class ReportTest extends TestCase
{
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-10-21 12:00:00');
        $this->admin = User::factory()->admin()->create();

        // Wed 14 Oct: 2 orders (3 + 1 units); Mon 19 Oct: 1 order (2 units); 30 Sep: 1 order (5 units).
        $this->sale('2026-10-14 09:00:00', '40.00', 3);
        $this->sale('2026-10-14 18:00:00', '20.00', 1);
        $this->sale('2026-10-19 10:00:00', '15.50', 2);
        $this->sale('2026-09-30 23:00:00', '100.00', 5);
        // Never counted: cancelled after payment, and an unpaid order.
        $this->sale('2026-10-14 10:00:00', '999.00', 9, OrderStatus::Cancelled);
        Order::factory()->create(['status' => OrderStatus::Pending, 'paid_at' => null, 'total' => '77.00']);
    }

    private function sale(string $paidAt, string $total, int $units, OrderStatus $status = OrderStatus::Delivered): void
    {
        $order = Order::factory()->create(['status' => $status, 'paid_at' => $paidAt, 'total' => $total, 'subtotal' => $total]);
        OrderItem::factory()->for($order)->create(['quantity' => $units]);
    }

    private function sales(ReportPeriod $period, string $firstDay, string $lastDay): SalesReport
    {
        return app(ReportService::class)->sales($period, CarbonImmutable::parse($firstDay), CarbonImmutable::parse($lastDay));
    }

    private function report(array $query): TestResponse
    {
        return $this->actingAs($this->admin)->get(route('reports.sales', $query, absolute: false));
    }

    public function test_daily_report_has_a_row_per_day_and_matching_totals(): void
    {
        $data = $this->sales(ReportPeriod::Day, '2026-10-13', '2026-10-15')->toArray();

        $this->assertSame([
            ['period' => '2026-10-13', 'orders' => 0, 'units' => 0, 'revenue' => '0.00', 'average' => '0.00'],
            ['period' => '2026-10-14', 'orders' => 2, 'units' => 4, 'revenue' => '60.00', 'average' => '30.00'],
            ['period' => '2026-10-15', 'orders' => 0, 'units' => 0, 'revenue' => '0.00', 'average' => '0.00'],
        ], $data['rows']);
        $this->assertSame(['orders' => 2, 'units' => 4, 'revenue' => '60.00', 'average' => '30.00'], $data['totals']);
    }

    public function test_weekly_and_monthly_reports_cover_whole_periods(): void
    {
        $weekly = $this->sales(ReportPeriod::Week, '2026-10-15', '2026-10-20');

        $this->assertSame(['2026-10-12', '2026-10-26'], [$weekly->from->toDateString(), $weekly->to->toDateString()]);
        $this->assertSame([['2026-10-12', 2, '60.00'], ['2026-10-19', 1, '15.50']], array_map(fn ($row) => [$row['period'], $row['orders'], $row['revenue']], $weekly->rows));

        $monthly = $this->sales(ReportPeriod::Month, '2026-09-15', '2026-10-10');

        $this->assertSame([['2026-09-01', '100.00', 5], ['2026-10-01', '75.50', 6]], array_map(fn ($row) => [$row['period'], $row['revenue'], $row['units']], $monthly->rows));
        $this->assertSame(['orders' => 4, 'units' => 11, 'revenue' => '175.50', 'average' => '43.88'], $monthly->totals);
    }

    public function test_the_page_defaults_to_the_last_30_days_and_links_the_exports(): void
    {
        $this->report([])
            ->assertOk()
            ->assertSee('22 Sep 2026')
            ->assertSee('21 Oct 2026')
            ->assertSee('175.50')
            ->assertSee('Wed 14 Oct 2026')
            ->assertSee('/reports/sales.pdf?period=day&date_from=2026-09-22&date_to=2026-10-21');
    }

    public function test_csv_export_has_one_line_per_period_and_a_total(): void
    {
        $response = $this->actingAs($this->admin)->get('/reports/sales.csv?period=week&date_from=2026-10-12&date_to=2026-10-25');

        $response->assertOk()->assertDownload('sales-week-2026-10-12-to-2026-10-25.csv');
        $this->assertSame([
            'period_start,period,orders,units,revenue,average_order_value',
            '2026-10-12,"Week of 12 Oct 2026",2,4,60.00,30.00',
            '2026-10-19,"Week of 19 Oct 2026",1,2,15.50,15.50',
            ',TOTAL,3,6,75.50,25.17',
        ], explode("\n", trim($response->streamedContent())));
    }

    public function test_pdf_export_is_a_real_pdf(): void
    {
        $response = $this->actingAs($this->admin)->get('/reports/sales.pdf?period=month&date_from=2026-09-01&date_to=2026-10-31');

        $response->assertOk()
            ->assertHeader('content-type', 'application/pdf')
            ->assertDownload('sales-month-2026-09-01-to-2026-10-31.pdf');
        $this->assertStringStartsWith('%PDF-', $response->getContent());
    }

    public function test_ranges_with_too_many_periods_or_backwards_dates_are_refused(): void
    {
        $this->report(['period' => 'day', 'date_from' => '2024-01-01', 'date_to' => '2026-01-01'])->assertSessionHasErrors('date_to');
        $this->report(['period' => 'month', 'date_from' => '2024-01-01', 'date_to' => '2026-01-01'])->assertSessionHasNoErrors()->assertOk();
        $this->report(['date_from' => '2026-10-10', 'date_to' => '2026-10-01'])->assertSessionHasErrors('date_to');
        $this->report(['period' => 'year'])->assertSessionHasErrors('period');
    }
}
