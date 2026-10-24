<?php

namespace App\Repositories;

use Illuminate\Support\Facades\Redis;

/** @throws \RedisException from every method when Redis is unreachable */
class RevokedTokenRepository
{
    private const TOKEN_KEY_PREFIX = 'auth:revoked-token:';

    private const USER_KEY_PREFIX = 'auth:user-tokens-valid-after:';

    // Outlives any token: the api-gateway caps JWT_EXPIRES_IN at a day.
    private const USER_CUTOFF_TTL_SECONDS = 86_400;

    public function isRevoked(string $jti, int $userId, int $issuedAt): bool
    {
        [$revoked, $validAfter] = Redis::connection('shared')->mget([self::TOKEN_KEY_PREFIX.$jti, self::USER_KEY_PREFIX.$userId]);

        return $revoked !== null || ($validAfter !== null && $issuedAt < (int) $validAfter);
    }

    public function revoke(string $jti, int $expiresAt): void
    {
        Redis::connection('shared')->set(self::TOKEN_KEY_PREFIX.$jti, '1', 'EX', max($expiresAt - time(), 1));
    }

    public function revokeAllForUser(int $userId): void
    {
        Redis::connection('shared')->set(self::USER_KEY_PREFIX.$userId, (string) time(), 'EX', self::USER_CUTOFF_TTL_SECONDS);
    }
}
