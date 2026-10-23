<?php

namespace Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase {
        migrateFreshUsing as defaultMigrateFreshUsing;
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
