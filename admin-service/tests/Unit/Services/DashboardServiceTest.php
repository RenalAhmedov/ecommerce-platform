<?php

namespace Tests\Unit\Services;

use App\Enums\ReportPeriod;
use App\Repositories\DashboardRepository;
use App\Repositories\SalesRepository;
use App\Services\DashboardService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;
use Mockery;
use Mockery\MockInterface;
use Tests\Unit\UnitTestCase;

class DashboardServiceTest extends UnitTestCase
{
    private DashboardRepository&MockInterface $dashboard;

    private SalesRepository&MockInterface $sales;

    protected function setUp(): void
    {
        parent::setUp();

        // Wednesday: the week started on Monday the 19th, the month on the 1st.
        Carbon::setTestNow('2026-10-21 12:00:00');
        $this->dashboard = $this->mock(DashboardRepository::class);
        $this->sales = $this->mock(SalesRepository::class);
    }

    private static function at(string $time): Mockery\Matcher\Closure
    {
        return Mockery::on(fn (CarbonImmutable $date) => $date->toDateTimeString() === $time);
    }

    private function givenRepositoryData(): void
    {
        $figures = ['orders' => 1, 'units' => 2, 'revenue' => '10.00', 'average' => '10.00'];
        $tomorrow = self::at('2026-10-22 00:00:00');

        $this->sales->shouldReceive('summary')->once()->with(self::at('2026-10-21 00:00:00'), $tomorrow)->andReturn($figures);
        $this->sales->shouldReceive('summary')->once()->with(self::at('2026-10-19 00:00:00'), $tomorrow)->andReturn($figures);
        $this->sales->shouldReceive('summary')->once()->with(self::at('2026-10-01 00:00:00'), $tomorrow)->andReturn($figures);
        $this->sales->shouldReceive('byPeriod')->once()
            ->with(ReportPeriod::Day, self::at('2026-10-08 00:00:00'), $tomorrow)
            ->andReturn([['period' => '2026-10-21', 'orders' => 1, 'units' => 2, 'revenue' => '10.00', 'average' => '10.00']]);
        $this->dashboard->shouldReceive('ordersByStatus')->once()->andReturn(['paid' => 1]);
        $this->dashboard->shouldReceive('customerCounts')->once()->with(self::at('2026-10-01 00:00:00'))->andReturn(['total' => 3, 'newSince' => 1]);
        $this->dashboard->shouldReceive('lowStock')->once()->with(10)->andReturn([]);
        $this->dashboard->shouldReceive('topSellers')->once()->with(self::at('2026-09-22 00:00:00'), 5)->andReturn([]);
        $this->dashboard->shouldReceive('recentOrders')->once()->with(8)->andReturn([]);
    }

    public function test_figures_are_built_for_today_this_week_and_this_month(): void
    {
        $this->givenRepositoryData();

        $metrics = app(DashboardService::class)->metrics();

        $this->assertSame('2026-10-21T12:00:00+00:00', $metrics['generatedAt']);
        $this->assertSame(['today', 'week', 'month'], array_keys($metrics['revenue']));
        $this->assertSame([['day' => '2026-10-21', 'revenue' => '10.00']], $metrics['dailyRevenue']);
        $this->assertSame(['total' => 3, 'newSince' => 1], $metrics['customers']);
    }

    public function test_figures_are_computed_once_and_then_served_from_the_cache(): void
    {
        $this->givenRepositoryData();
        $service = app(DashboardService::class);

        $first = $service->metrics();
        Carbon::setTestNow(now()->addSeconds(DashboardService::FRESH_SECONDS - 1));

        $this->assertSame($first, $service->metrics());
    }
}
