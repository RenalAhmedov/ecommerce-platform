<?php

namespace Tests\Feature\Support;

use App\Support\AdvisoryLock;
use Illuminate\Support\Facades\DB;
use LogicException;
use Tests\Feature\FeatureTestCase;

class AdvisoryLockTest extends FeatureTestCase
{
    public function test_keys_match_the_nest_api_format(): void
    {
        $this->assertSame('products.sku:ABC-1', AdvisoryLock::productSku('ABC-1'));
        $this->assertSame('products.slug:blue-mug', AdvisoryLock::productSlug('blue-mug'));
        $this->assertSame('users.email:ada@example.test', AdvisoryLock::userEmail('Ada@Example.TEST'));
    }

    public function test_locks_are_held_by_the_current_transaction(): void
    {
        DB::transaction(function () {
            AdvisoryLock::acquire([AdvisoryLock::productSku('ABC-1')]);

            $held = DB::scalar(
                "SELECT count(*) FROM pg_locks WHERE locktype = 'advisory' AND pid = pg_backend_pid() AND objid = hashtext(?)::oid",
                [AdvisoryLock::productSku('ABC-1')],
            );

            $this->assertSame(1, $held);
        });
    }

    public function test_it_refuses_to_lock_outside_a_transaction(): void
    {
        DB::rollBack();

        try {
            $this->expectException(LogicException::class);
            AdvisoryLock::acquire(['x']);
        } finally {
            DB::beginTransaction();
        }
    }
}
