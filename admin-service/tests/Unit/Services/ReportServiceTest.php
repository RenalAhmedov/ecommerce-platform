<?php

namespace Tests\Unit\Services;

use App\Enums\ReportPeriod;
use App\Repositories\SalesRepository;
use App\Services\ReportService;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;
use Mockery;
use Mockery\MockInterface;
use Tests\Unit\UnitTestCase;

class ReportServiceTest extends UnitTestCase
{
    private const TOTALS = ['orders' => 1, 'units' => 1, 'revenue' => '5.00', 'average' => '5.00'];

    private SalesRepository&MockInterface $sales;

    private ReportService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sales = $this->mock(SalesRepository::class);
        $this->service = app(ReportService::class);
    }

    private static function on(string $date): Mockery\Matcher\Closure
    {
        return Mockery::on(fn (CarbonImmutable $value) => $value->toDateTimeString() === "{$date} 00:00:00");
    }

    private function expectRange(ReportPeriod $period, string $from, string $to): void
    {
        $this->sales->shouldReceive('byPeriod')->once()->with($period, self::on($from), self::on($to))->andReturn([]);
        $this->sales->shouldReceive('summary')->once()->with(self::on($from), self::on($to))->andReturn(self::TOTALS);
    }

    public function test_a_weekly_report_is_widened_to_whole_weeks(): void
    {
        $this->expectRange(ReportPeriod::Week, '2026-10-12', '2026-10-26');

        $report = $this->service->sales(ReportPeriod::Week, CarbonImmutable::parse('2026-10-15'), CarbonImmutable::parse('2026-10-20'));

        $this->assertSame(['2026-10-12', '2026-10-25'], [$report->from->toDateString(), $report->lastDay()->toDateString()]);
        $this->assertSame(self::TOTALS, $report->totals);
    }

    public function test_a_monthly_report_is_widened_to_whole_months(): void
    {
        $this->expectRange(ReportPeriod::Month, '2026-09-01', '2026-11-01');

        $this->service->sales(ReportPeriod::Month, CarbonImmutable::parse('2026-09-15'), CarbonImmutable::parse('2026-10-10'));
    }

    public function test_a_daily_report_includes_its_last_day(): void
    {
        $this->expectRange(ReportPeriod::Day, '2026-10-01', '2026-10-04');

        $this->service->sales(ReportPeriod::Day, CarbonImmutable::parse('2026-10-01'), CarbonImmutable::parse('2026-10-03'));
    }

    public function test_too_many_periods_are_refused_before_querying(): void
    {
        $this->sales->shouldNotReceive('byPeriod', 'summary');

        try {
            $this->service->sales(ReportPeriod::Day, CarbonImmutable::parse('2024-01-01'), CarbonImmutable::parse('2026-01-01'));
            $this->fail('A two-year daily report was allowed.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('at most 366 periods', $e->errors()['date_to'][0]);
        }
    }
}
