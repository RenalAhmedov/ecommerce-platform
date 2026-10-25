<?php

namespace App\Services;

use App\Enums\ReportPeriod;
use App\Repositories\DashboardRepository;
use App\Repositories\SalesRepository;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Cache\Repository as Cache;

class DashboardService
{
    public const FRESH_SECONDS = 30;

    public const STALE_SECONDS = 300;

    private const CACHE_KEY = 'dashboard:metrics';

    private const CHART_DAYS = 14;

    private const TOP_SELLER_DAYS = 30;

    public function __construct(
        private readonly DashboardRepository $dashboard,
        private readonly SalesRepository $sales,
        private readonly Cache $cache,
    ) {}

    /**
     * @return array{
     *     generatedAt: string,
     *     revenue: array{today: array{orders: int, units: int, revenue: string, average: string}, week: array{orders: int, units: int, revenue: string, average: string}, month: array{orders: int, units: int, revenue: string, average: string}},
     *     dailyRevenue: list<array{day: string, revenue: string}>,
     *     ordersByStatus: array<string, int>,
     *     customers: array{total: int, newSince: int},
     *     lowStock: list<array{id: int, sku: string, name: string, quantity: int, threshold: int}>,
     *     topSellers: list<array{id: int, sku: string, name: string, units: int, revenue: string}>,
     *     recentOrders: list<array{id: int, orderNumber: string, customer: string, total: string, status: string, createdAt: string}>
     * }
     */
    public function metrics(): array
    {
        // Fresh for 30 s, then served stale for up to 5 min while one background refresh recomputes.
        return $this->cache->flexible(self::CACHE_KEY, [self::FRESH_SECONDS, self::STALE_SECONDS], fn () => $this->compute());
    }

    /** @return array<string, mixed> */
    private function compute(): array
    {
        $now = CarbonImmutable::now();
        $today = $now->startOfDay();
        $tomorrow = $today->addDay();

        return [
            'generatedAt' => $now->toIso8601String(),
            'revenue' => [
                'today' => $this->sales->summary($today, $tomorrow),
                'week' => $this->sales->summary($now->startOfWeek(), $tomorrow),
                'month' => $this->sales->summary($now->startOfMonth(), $tomorrow),
            ],
            'dailyRevenue' => array_map(
                fn (array $day) => ['day' => $day['period'], 'revenue' => $day['revenue']],
                $this->sales->byPeriod(ReportPeriod::Day, $today->subDays(self::CHART_DAYS - 1), $tomorrow),
            ),
            'ordersByStatus' => $this->dashboard->ordersByStatus(),
            'customers' => $this->dashboard->customerCounts($now->startOfMonth()),
            'lowStock' => $this->dashboard->lowStock(10),
            'topSellers' => $this->dashboard->topSellers($today->subDays(self::TOP_SELLER_DAYS - 1), 5),
            'recentOrders' => $this->dashboard->recentOrders(8),
        ];
    }
}
