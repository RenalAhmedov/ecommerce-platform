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
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;
use UnexpectedValueException;

class JwtAuthService
{
    private const ALGORITHM = 'HS256';

    private const MIN_SECRET_BYTES = 32;

    public function __construct(
        private readonly UserRepository $users,
        private readonly RevokedTokenRepository $revokedTokens,
        #[Config('auth.jwt.secret')] private readonly ?string $secret,
    ) {}

    public function userFromToken(?string $token): ?User
    {
        $claims = $this->verify($token);

        return $claims === null ? null : $this->users->findById((int) $claims->sub);
    }

    public function verify(?string $token): ?object
    {
        $claims = $this->claims($token);

        if ($claims === null) {
            return null;
        }

        if ($this->isRevoked($claims)) {
            Log::warning('Revoked access token used', ['jti' => $claims->jti, 'sub' => $claims->sub]);

            return null;
        }

        return $claims;
    }

    public function isActive(object $claims): bool
    {
        return $claims->exp > time() && ! $this->isRevoked($claims);
    }

    public function logout(string $token): void
    {
        $claims = $this->claims($token) ?? throw new LogicException('logout() needs a token the jwt guard has accepted.');

        try {
            $this->revoke($claims);
        } catch (RedisException $e) {
            Log::error('Logout failed: Redis unavailable.', ['jti' => $claims->jti, 'error' => $e->getMessage()]);

            throw new ServiceUnavailableHttpException(message: 'Could not log out right now, please retry', previous: $e);
        }
    }

    /** @throws RedisException */
    public function revoke(object $claims): void
    {
        $this->revokedTokens->revoke($claims->jti, $claims->exp);

        Log::info('Access token revoked by logout', ['jti' => $claims->jti, 'sub' => $claims->sub]);
    }

    // Fails closed (unlike isRevoked): the caller's change rolls back rather than leave a deleted user a working token.
    /** @throws RedisException */
    public function revokeAllForUser(int $userId): void
    {
        $this->revokedTokens->revokeAllForUser($userId);

        Log::info('All access tokens of the user revoked', ['target_user_id' => $userId]);
    }

    private function claims(?string $token): ?object
    {
        if ($token === null || $token === '') {
            return null;
        }

        // php-jwt refuses HS256 keys under 32 bytes; that must surface as a configuration
        // error, not be mistaken for a bad token (which would reject every login silently).
        if (blank($this->secret) || strlen($this->secret) < self::MIN_SECRET_BYTES) {
            throw new LogicException('JWT_SECRET must be set and at least '.self::MIN_SECRET_BYTES.' bytes long (the same secret as the Nest api-gateway).');
        }

        try {
            $claims = JWT::decode($token, new Key($this->secret, self::ALGORITHM));
        } catch (UnexpectedValueException|DomainException) {
            return null;
        }

        $valid = is_numeric($claims->sub ?? null) && is_string($claims->jti ?? null)
            && is_int($claims->iat ?? null) && is_int($claims->exp ?? null);

        return $valid ? $claims : null;
    }

    // Fails open, like the api-gateway's guard: Redis being down must not lock every admin out.
    private function isRevoked(object $claims): bool
    {
        try {
            return $this->revokedTokens->isRevoked($claims->jti, (int) $claims->sub, $claims->iat);
        } catch (RedisException $e) {
            Log::warning('Token revocation check skipped: Redis unavailable.', ['error' => $e->getMessage()]);

            return false;
        }
    }
}
