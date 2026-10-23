<?php

namespace App\Enums;

use Carbon\CarbonImmutable;

/** Grouping of a sales report. The value is also the Postgres date_trunc() unit. */
enum ReportPeriod: string
{
    case Day = 'day';
    case Week = 'week';
    case Month = 'month';

    public function startOf(CarbonImmutable $date): CarbonImmutable
    {
        return match ($this) {
            self::Day => $date->startOfDay(),
            self::Week => $date->startOfWeek(),
            self::Month => $date->startOfMonth(),
        };
    }

    /** Start of the next period: reports use [start, end) ranges. */
    public function endOf(CarbonImmutable $date): CarbonImmutable
    {
        return match ($this) {
            self::Day => $date->startOfDay()->addDay(),
            self::Week => $date->startOfWeek()->addWeek(),
            self::Month => $date->startOfMonth()->addMonth(),
        };
    }

    /** Keeps a report (and its PDF) to a readable size: about a year of days, five years of weeks, ten of months. */
    public function maxPeriods(): int
    {
        return match ($this) {
            self::Day => 366,
            self::Week => 260,
            self::Month => 120,
        };
    }

    public function format(CarbonImmutable $periodStart): string
    {
        return match ($this) {
            self::Day => $periodStart->format('D j M Y'),
            self::Week => 'Week of '.$periodStart->format('j M Y'),
            self::Month => $periodStart->format('F Y'),
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Day => 'Daily',
            self::Week => 'Weekly',
            self::Month => 'Monthly',
        };
    }
}
