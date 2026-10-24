<?php

namespace Tests\Feature\Api;

use Tests\Feature\Api\Concerns\AuthenticatesWithNestToken;
use Tests\Feature\FeatureTestCase;

class DashboardApiTest extends FeatureTestCase
{
    use AuthenticatesWithNestToken;

    public function test_the_dashboard_figures_are_available_as_json(): void
    {
        $this->api()->getJson('/admin/api/dashboard')
            ->assertOk()
            ->assertJsonStructure(['data' => ['generatedAt', 'revenue' => ['today', 'week', 'month'], 'dailyRevenue', 'ordersByStatus', 'customers', 'lowStock', 'topSellers', 'recentOrders']]);
    }
}
