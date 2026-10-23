<?php

namespace App\Services;

use App\Enums\ReportPeriod;
use App\Reports\SalesReport;
use App\Repositories\SalesRepository;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

class ReportService
{
    public function __construct(private readonly SalesRepository $sales) {}

    /**
     * The range is widened to whole periods (a monthly report from 15 Oct to
     * 10 Nov covers all of October and November), so every row is a complete
     * day, week or month and rows add up to the totals.
     *
     * @throws ValidationException when the range has more periods than the report allows
     */
    public function sales(ReportPeriod $period, CarbonImmutable $firstDay, CarbonImmutable $lastDay): SalesReport
    {
        $from = $period->startOf($firstDay);
        $to = $period->endOf($lastDay);

        $periods = (int) round(match ($period) {
            ReportPeriod::Day => $from->diffInDays($to),
            ReportPeriod::Week => $from->diffInWeeks($to),
            ReportPeriod::Month => $from->diffInMonths($to),
        });

        if ($periods > $period->maxPeriods()) {
            throw ValidationException::withMessages([
                'date_to' => "A {$period->label()} report can cover at most {$period->maxPeriods()} periods; this range has {$periods}. Choose a shorter range or a longer period.",
            ]);
        }

        return new SalesReport(
            period: $period,
            from: $from,
            to: $to,
            rows: $this->sales->byPeriod($period, $from, $to),
            totals: $this->sales->summary($from, $to),
            generatedAt: CarbonImmutable::now(),
        );
    }
}
