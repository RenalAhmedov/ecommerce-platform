<?php

namespace Tests\Feature;

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\TestServices;
use Tests\TestCase;

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
