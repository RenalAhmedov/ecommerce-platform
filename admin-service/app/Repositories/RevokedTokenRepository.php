<?php

namespace App\Repositories;

use Illuminate\Support\Facades\Redis;

/**
 * The Nest api-gateway writes auth:revoked-token:<jti> on logout (TTL = rest of
 * the token's life). Reading the same key makes a logout count in both services.
 */
class RevokedTokenRepository
{
    private const KEY_PREFIX = 'auth:revoked-token:';

    /** @throws \RedisException when Redis is unreachable */
    public function isRevoked(string $jti): bool
    {
        return (bool) Redis::connection('shared')->exists(self::KEY_PREFIX.$jti);
    }
}
