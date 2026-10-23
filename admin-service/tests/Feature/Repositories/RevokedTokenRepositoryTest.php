<?php

namespace Tests\Feature\Repositories;

use App\Repositories\RevokedTokenRepository;
use Illuminate\Support\Str;
use Redis;
use Tests\Feature\FeatureTestCase;

/** Uses the real Redis: the key must be exactly what the Nest api-gateway writes. */
class RevokedTokenRepositoryTest extends FeatureTestCase
{
    public function test_it_reads_the_unprefixed_key_written_by_nest(): void
    {
        $jti = 'test-'.Str::uuid();
        $nest = new Redis;
        $nest->connect(config('database.redis.shared.host'), (int) config('database.redis.shared.port'));

        $repository = app(RevokedTokenRepository::class);

        try {
            $this->assertFalse($repository->isRevoked($jti));

            $nest->set("auth:revoked-token:{$jti}", '1', ['EX' => 60]);

            $this->assertTrue($repository->isRevoked($jti));
        } finally {
            $nest->del("auth:revoked-token:{$jti}");
            $nest->close();
        }
    }
}
