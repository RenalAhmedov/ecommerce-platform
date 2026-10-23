<?php

namespace Tests\Feature\Api\Concerns;

use App\Models\User;
use App\Repositories\RevokedTokenRepository;
use Firebase\JWT\JWT;
use Illuminate\Support\Str;
use Mockery\MockInterface;

/**
 * API tests call the admin API with access tokens built exactly like the Nest
 * api-gateway builds them. The revocation check is mocked (no token is revoked
 * unless a test says so); RevokedTokenRepositoryTest covers the real Redis key.
 * Laravel runs setUpAuthenticatesWithNestToken() before every test.
 */
trait AuthenticatesWithNestToken
{
    protected User $admin;

    protected RevokedTokenRepository&MockInterface $revokedTokens;

    protected function setUpAuthenticatesWithNestToken(): void
    {
        $this->admin = User::factory()->admin()->create();
        $this->revokedTokens = $this->mock(RevokedTokenRepository::class);
        $this->revokedTokens->shouldReceive('isRevoked')->andReturnFalse()->byDefault();
    }

    /** @param  array<string, mixed>  $overrides  claims to change; null removes a claim */
    protected function tokenFor(User $user, array $overrides = [], ?string $secret = null): string
    {
        $claims = [
            'sub' => $user->id,
            'email' => $user->email,
            'role' => $user->role->value,
            'jti' => (string) Str::uuid(),
            'iat' => time(),
            'exp' => time() + 3600,
            ...$overrides,
        ];

        return JWT::encode(array_filter($claims, fn ($value) => $value !== null), $secret ?? config('auth.jwt.secret'), 'HS256');
    }

    /** Calls the API as the admin, or with the given token. */
    protected function api(?string $token = null): static
    {
        return $this->withToken($token ?? $this->tokenFor($this->admin));
    }
}
