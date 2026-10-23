<?php

namespace App\Services;

use App\Repositories\DashboardRepository;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Cache\Repository as Cache;

/**
 * Dashboard figures, cached in Redis with "stale-while-revalidate": fresh for
 * 30 s; after that the previous figures are still served instantly for up to
 * 5 minutes while a single background refresh recomputes them, so neither a
 * slow page nor a burst of recomputations ("cache stampede") ever happens.
 * Orders are also written by the Nest api-gateway, so a short lifetime is used
 * instead of invalidating on every change. All periods are in the app timezone (UTC).
 */
class DashboardService
{
    public const FRESH_SECONDS = 30;

    public const STALE_SECONDS = 300;

    private const CACHE_KEY = 'dashboard:metrics';

    private const CHART_DAYS = 14;

    private const TOP_SELLER_DAYS = 30;

    public function __construct(
        private readonly DashboardRepository $dashboard,
        private readonly Cache $cache,
    ) {}

    /**
     * @return array{
     *     generatedAt: string,
     *     revenue: array{today: array{orders: int, revenue: string, average: string}, week: array{orders: int, revenue: string, average: string}, month: array{orders: int, revenue: string, average: string}},
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
                'today' => $this->dashboard->revenueBetween($today, $tomorrow),
                'week' => $this->dashboard->revenueBetween($now->startOfWeek(), $tomorrow),
                'month' => $this->dashboard->revenueBetween($now->startOfMonth(), $tomorrow),
            ],
            'dailyRevenue' => $this->dashboard->dailyRevenue($today->subDays(self::CHART_DAYS - 1), $today),
            'ordersByStatus' => $this->dashboard->ordersByStatus(),
            'customers' => $this->dashboard->customerCounts($now->startOfMonth()),
            'lowStock' => $this->dashboard->lowStock(10),
            'topSellers' => $this->dashboard->topSellers($today->subDays(self::TOP_SELLER_DAYS - 1), 5),
            'recentOrders' => $this->dashboard->recentOrders(8),
        ];
    }
}
