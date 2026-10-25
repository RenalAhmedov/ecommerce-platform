<?php

namespace Tests\Feature\Api;

use Tests\Feature\Api\Concerns\AuthenticatesWithNestToken;
use Tests\Feature\FeatureTestCase;

class ReportApiTest extends FeatureTestCase
{
    use AuthenticatesWithNestToken;

    public function test_sales_report_as_json(): void
    {
        $this->api()->getJson('/admin/api/reports/sales?period=month&date_from=2026-01-01&date_to=2026-03-31')
            ->assertOk()
            ->assertJsonPath('data.period', 'month')
            ->assertJsonPath('data.from', '2026-01-01')
            ->assertJsonPath('data.to', '2026-03-31')
            ->assertJsonCount(3, 'data.rows')
            ->assertJsonPath('data.totals.revenue', '0.00');

        $this->api()->getJson('/admin/api/reports/sales?period=day&date_from=2020-01-01&date_to=2026-01-01')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('date_to');
    }
}
