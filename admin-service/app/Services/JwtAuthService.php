<?php

namespace App\Services;

use App\Models\User;
use App\Repositories\RevokedTokenRepository;
use App\Repositories\UserRepository;
use DomainException;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Container\Attributes\Config;
use Illuminate\Support\Facades\Log;
use LogicException;
use RedisException;
use UnexpectedValueException;

/**
 * Verifies access tokens issued by the Nest api-gateway (HS256, shared secret,
 * claims { sub, email, role, jti, iat, exp }), so one token works for both services.
 */
class JwtAuthService
{
    private const ALGORITHM = 'HS256';

    public function __construct(
        private readonly UserRepository $users,
        private readonly RevokedTokenRepository $revokedTokens,
        #[Config('auth.jwt.secret')] private readonly ?string $secret,
    ) {}

    public function userFromToken(?string $token): ?User
    {
        if ($token === null || $token === '') {
            return null;
        }

        $claims = $this->decode($token);

        if ($claims === null || ! is_numeric($claims->sub ?? null) || ! is_string($claims->jti ?? null)) {
            return null;
        }

        if ($this->isRevoked($claims->jti)) {
            return null;
        }

        return $this->users->findById((int) $claims->sub);
    }

    /** Null for a bad signature, an expired or malformed token, or another algorithm. */
    private function decode(string $token): ?object
    {
        if (blank($this->secret)) {
            throw new LogicException('JWT_SECRET is not configured.');
        }

        try {
            return JWT::decode($token, new Key($this->secret, self::ALGORITHM));
        } catch (UnexpectedValueException|DomainException) {
            return null;
        }
    }

    /** Fails open like the Nest guard: Redis being down must not lock every admin out. */
    private function isRevoked(string $jti): bool
    {
        try {
            return $this->revokedTokens->isRevoked($jti);
        } catch (RedisException $e) {
            Log::warning('Token revocation check skipped: Redis unavailable.', ['error' => $e->getMessage()]);

            return false;
        }
    }
}
