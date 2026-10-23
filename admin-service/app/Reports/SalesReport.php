<?php

namespace App\Reports;

use App\Enums\ReportPeriod;
use Carbon\CarbonImmutable;

/** A finished sales report: what the page, the CSV, the PDF and the API all render. */
final readonly class SalesReport
{
    /**
     * @param  list<array{period: string, orders: int, units: int, revenue: string, average: string}>  $rows
     * @param  array{orders: int, units: int, revenue: string, average: string}  $totals
     */
    public function __construct(
        public ReportPeriod $period,
        public CarbonImmutable $from,
        public CarbonImmutable $to,
        public array $rows,
        public array $totals,
        public CarbonImmutable $generatedAt,
    ) {}

    /** The last day covered (the range itself is [from, to)). */
    public function lastDay(): CarbonImmutable
    {
        return $this->to->subDay();
    }

    public function rowLabel(array $row): string
    {
        return $this->period->format(CarbonImmutable::parse($row['period']));
    }

    public function maxRevenue(): float
    {
        return max([0.0, ...array_map(fn (array $row) => (float) $row['revenue'], $this->rows)]);
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'period' => $this->period->value,
            'from' => $this->from->toDateString(),
            'to' => $this->lastDay()->toDateString(),
            'rows' => $this->rows,
            'totals' => $this->totals,
            'generatedAt' => $this->generatedAt->toIso8601String(),
        ];
    }
}
