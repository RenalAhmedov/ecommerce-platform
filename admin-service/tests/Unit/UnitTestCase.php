<?php

namespace Tests\Unit;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Unit tests: one class at a time, its collaborators mocked, no database,
 * Redis or containers. Transactions run their callback inline, and any other
 * database access fails loudly, so a missing mock can't silently hit a real database.
 */
abstract class UnitTestCase extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['database.default' => 'unit-tests-have-no-database']);
        DB::shouldReceive('transaction')->andReturnUsing(fn (callable $callback) => $callback());
    }
}
