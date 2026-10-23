<?php

namespace Tests\Feature;

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\TestServices;
use Tests\TestCase;

/**
 * Integration tests: the real application over HTTP, against real Postgres
 * and Redis in throwaway containers. The full schema (the shared tables the
 * Nest service owns plus this service's own) is built once per run, and each
 * test runs in a transaction that is rolled back afterwards.
 */
abstract class FeatureTestCase extends TestCase
{
    use RefreshDatabase {
        migrateFreshUsing as defaultMigrateFreshUsing;
    }

    public function createApplication(): Application
    {
        TestServices::start();

        $app = parent::createApplication();
        TestServices::assertIsTarget($app['config']);

        return $app;
    }

    /** Pages are tested without the built CSS/JS, so tests don't need `npm run build` first. */
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    /** @return array<string, mixed> */
    protected function migrateFreshUsing(): array
    {
        return [
            ...$this->defaultMigrateFreshUsing(),
            '--path' => ['database/migrations/shared', 'database/migrations'],
        ];
    }
}
