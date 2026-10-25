<?php

namespace Tests\Unit;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

abstract class UnitTestCase extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['database.default' => 'unit-tests-have-no-database']);
        DB::shouldReceive('transaction')->andReturnUsing(fn (callable $callback) => $callback());
    }
}
