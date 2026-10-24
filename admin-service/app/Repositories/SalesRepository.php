<?php

namespace App\Repositories;

use App\Enums\OrderStatus;
use App\Enums\ReportPeriod;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class SalesRepository
{
    /** @return array{orders: int, units: int, revenue: string, average: string} */
    public function summary(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $row = DB::selectOne(
            'WITH paid AS ('.$this->paidOrdersSql().')
             SELECT count(*) AS orders,
                    coalesce((SELECT sum(quantity) FROM order_items WHERE order_id IN (SELECT id FROM paid)), 0) AS units,
                    coalesce(sum(total), 0)::numeric(14, 2) AS revenue,
                    coalesce(avg(total), 0)::numeric(14, 2) AS average
             FROM paid',
            $this->paidOrdersBindings($from, $to),
        );

        return $this->toFigures($row);
    }

    /** @return list<array{period: string, orders: int, units: int, revenue: string, average: string}> */
    public function byPeriod(ReportPeriod $period, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $rows = DB::select(
            "WITH paid AS ({$this->paidOrdersSql()}),
             periods AS (
                SELECT generate_series(date_trunc(?, ?::timestamptz), ?::timestamptz - interval '1 second', ('1 ' || ?)::interval) AS period
             ),
             revenue AS (
                SELECT date_trunc(?, paid_at) AS period, count(*) AS orders, sum(total) AS revenue, avg(total) AS average
                FROM paid GROUP BY 1
             ),
             units AS (
                SELECT date_trunc(?, p.paid_at) AS period, sum(oi.quantity) AS units
                FROM paid p JOIN order_items oi ON oi.order_id = p.id GROUP BY 1
             )
             SELECT to_char(periods.period, 'YYYY-MM-DD') AS period,
                    coalesce(r.orders, 0) AS orders,
                    coalesce(u.units, 0) AS units,
                    coalesce(r.revenue, 0)::numeric(14, 2) AS revenue,
                    coalesce(r.average, 0)::numeric(14, 2) AS average
             FROM periods
             LEFT JOIN revenue r ON r.period = periods.period
             LEFT JOIN units u ON u.period = periods.period
             ORDER BY periods.period",
            [
                ...$this->paidOrdersBindings($from, $to),
                $period->value, $from->toIso8601String(), $to->toIso8601String(), $period->value,
                $period->value,
                $period->value,
            ],
        );

        return array_map(fn (object $row) => ['period' => $row->period, ...$this->toFigures($row)], $rows);
    }

    private function paidOrdersSql(): string
    {
        $statuses = implode(', ', array_fill(0, count(OrderStatus::revenueStatuses()), '?'));

        return "SELECT id, total, paid_at FROM orders WHERE status IN ({$statuses}) AND paid_at >= ? AND paid_at < ?";
    }

    /** @return list<string> */
    private function paidOrdersBindings(CarbonImmutable $from, CarbonImmutable $to): array
    {
        return [
            ...array_map(fn (OrderStatus $status) => $status->value, OrderStatus::revenueStatuses()),
            $from->toIso8601String(),
            $to->toIso8601String(),
        ];
    }

    /** @return array{orders: int, units: int, revenue: string, average: string} */
    private function toFigures(object $row): array
    {
        return [
            'orders' => (int) $row->orders,
            'units' => (int) $row->units,
            'revenue' => (string) $row->revenue,
            'average' => (string) $row->average,
        ];
    }
}
