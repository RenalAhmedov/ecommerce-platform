<?php

namespace Tests\Feature\Repositories;

use App\Repositories\RevokedTokenRepository;
use Illuminate\Support\Str;
use Redis;
use Tests\Feature\FeatureTestCase;

class RevokedTokenRepositoryTest extends FeatureTestCase
{
    private Redis $nest;

    private RevokedTokenRepository $repository;

    // Never a real user id, so no other test's keys can interfere.
    private int $userId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->nest = new Redis;
        $this->nest->connect(config('database.redis.shared.host'), (int) config('database.redis.shared.port'));
        $this->repository = app(RevokedTokenRepository::class);
        $this->userId = random_int(1_000_000_000, 2_000_000_000);
    }

    protected function tearDown(): void
    {
        $this->nest->del("auth:user-tokens-valid-after:{$this->userId}");
        $this->nest->close();

        parent::tearDown();
    }

    public function test_it_reads_a_logout_written_by_nest(): void
    {
        $jti = 'test-'.Str::uuid();

        $this->assertFalse($this->repository->isRevoked($jti, $this->userId, time()));

        $this->nest->set("auth:revoked-token:{$jti}", '1', ['EX' => 60]);

        $this->assertTrue($this->repository->isRevoked($jti, $this->userId, time()));
    }

    public function test_a_logout_is_written_the_way_nest_writes_it(): void
    {
        $jti = 'test-'.Str::uuid();

        $this->repository->revoke($jti, time() + 1_800);

        $this->assertSame('1', $this->nest->get("auth:revoked-token:{$jti}"));
        $this->assertEqualsWithDelta(1_800, $this->nest->ttl("auth:revoked-token:{$jti}"), 2);
    }

    public function test_revoking_a_user_kills_only_the_tokens_issued_before(): void
    {
        $this->repository->revokeAllForUser($this->userId);
        $cutoff = (int) $this->nest->get("auth:user-tokens-valid-after:{$this->userId}");

        $this->assertEqualsWithDelta(time(), $cutoff, 2);
        $this->assertEqualsWithDelta(86_400, $this->nest->ttl("auth:user-tokens-valid-after:{$this->userId}"), 2);
        $this->assertTrue($this->repository->isRevoked('older', $this->userId, $cutoff - 1));
        $this->assertFalse($this->repository->isRevoked('newer', $this->userId, $cutoff));
        $this->assertFalse($this->repository->isRevoked('other-user', $this->userId + 1, $cutoff - 1));
    }
}
