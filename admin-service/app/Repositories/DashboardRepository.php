<?php

namespace App\Repositories;

use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Models\Inventory;
use App\Models\Order;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Read-only aggregate queries. Everything returns plain arrays: the dashboard
 * caches them in Redis, and the cache only stores plain data, never objects.
 */
class DashboardRepository
{
    /** @return array{orders: int, revenue: string, average: string} orders paid in [$from, $to) */
    public function revenueBetween(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $row = Order::query()
            ->whereIn('status', OrderStatus::revenueStatuses())
            ->where('paid_at', '>=', $from)
            ->where('paid_at', '<', $to)
            ->selectRaw('count(*) AS orders, coalesce(sum(total), 0)::numeric(14, 2) AS revenue, coalesce(avg(total), 0)::numeric(14, 2) AS average')
            ->toBase()
            ->first();

        return ['orders' => (int) $row->orders, 'revenue' => (string) $row->revenue, 'average' => (string) $row->average];
    }

    /**
     * One row per day, including days without sales (generate_series).
     *
     * @return list<array{day: string, revenue: string}>
     */
    public function dailyRevenue(CarbonImmutable $fromDay, CarbonImmutable $toDay): array
    {
        $rows = DB::select(
            "SELECT day::date AS day, coalesce(sum(o.total), 0)::numeric(14, 2) AS revenue
             FROM generate_series(?::date, ?::date, interval '1 day') AS day
             LEFT JOIN orders o
               ON o.paid_at >= day AND o.paid_at < day + interval '1 day'
              AND o.status IN (".implode(',', array_fill(0, count(OrderStatus::revenueStatuses()), '?')).')
             GROUP BY day
             ORDER BY day',
            [$fromDay->toDateString(), $toDay->toDateString(), ...array_map(fn ($status) => $status->value, OrderStatus::revenueStatuses())],
        );

        return array_map(fn ($row) => ['day' => $row->day, 'revenue' => (string) $row->revenue], $rows);
    }

    /** @return array<string, int> status => number of orders, every status included */
    public function ordersByStatus(): array
    {
        $counts = Order::query()->selectRaw('status, count(*) AS total')->groupBy('status')->toBase()->pluck('total', 'status');

        return collect(OrderStatus::cases())
            ->mapWithKeys(fn (OrderStatus $status) => [$status->value => (int) ($counts[$status->value] ?? 0)])
            ->all();
    }

    /** @return array{total: int, newSince: int} */
    public function customerCounts(CarbonImmutable $since): array
    {
        $customers = User::query()->where('role', UserRole::Customer);

        return [
            'total' => (clone $customers)->count(),
            'newSince' => (clone $customers)->where('created_at', '>=', $since)->count(),
        ];
    }

    /**
     * Active products at or below their low-stock threshold, emptiest first.
     *
     * @return list<array{id: int, sku: string, name: string, quantity: int, threshold: int}>
     */
    public function lowStock(int $limit): array
    {
        return Inventory::query()
            ->join('products', 'products.id', '=', 'inventories.product_id')
            ->whereNull('products.deleted_at')
            ->where('products.is_active', true)
            ->whereColumn('inventories.quantity', '<=', 'inventories.low_stock_threshold')
            ->orderBy('inventories.quantity')
            ->orderBy('products.name')
            ->limit($limit)
            ->get(['products.id', 'products.sku', 'products.name', 'inventories.quantity', 'inventories.low_stock_threshold AS threshold'])
            ->map(fn ($row) => ['id' => $row->id, 'sku' => $row->sku, 'name' => $row->name, 'quantity' => (int) $row->quantity, 'threshold' => (int) $row->threshold])
            ->all();
    }

    /**
     * Best sellers by units in orders paid since $since (deleted products included: they were still sold).
     *
     * @return list<array{id: int, sku: string, name: string, units: int, revenue: string}>
     */
    public function topSellers(CarbonImmutable $since, int $limit): array
    {
        return DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->join('products', 'products.id', '=', 'order_items.product_id')
            ->whereIn('orders.status', array_map(fn ($status) => $status->value, OrderStatus::revenueStatuses()))
            ->where('orders.paid_at', '>=', $since)
            ->groupBy('products.id', 'products.sku', 'products.name')
            ->orderByDesc('units')
            ->orderBy('products.name')
            ->limit($limit)
            ->get(['products.id', 'products.sku', 'products.name', DB::raw('sum(order_items.quantity) AS units'), DB::raw('sum(order_items.line_total)::numeric(14, 2) AS revenue')])
            ->map(fn ($row) => ['id' => $row->id, 'sku' => $row->sku, 'name' => $row->name, 'units' => (int) $row->units, 'revenue' => (string) $row->revenue])
            ->all();
    }

    /** @return list<array{id: int, orderNumber: string, customer: string, total: string, status: string, createdAt: string}> */
    public function recentOrders(int $limit): array
    {
        return Order::query()
            ->with(['user' => fn ($query) => $query->withTrashed()])
            ->latest('id')
            ->limit($limit)
            ->get()
            ->map(fn (Order $order) => [
                'id' => $order->id,
                'orderNumber' => $order->order_number,
                'customer' => $order->user->name,
                'total' => $order->total,
                'status' => $order->status->value,
                'createdAt' => $order->created_at->toIso8601String(),
            ])
            ->all();
    }
}
