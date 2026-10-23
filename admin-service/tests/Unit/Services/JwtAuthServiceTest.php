<?php

namespace Tests\Unit\Services;

use App\Models\User;
use App\Repositories\RevokedTokenRepository;
use App\Repositories\UserRepository;
use App\Services\JwtAuthService;
use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Log;
use LogicException;
use Mockery\MockInterface;
use RedisException;
use Tests\Unit\UnitTestCase;

class JwtAuthServiceTest extends UnitTestCase
{
    /** 64 bytes, so a token signed with the same secret but HS512 can be built for the algorithm test. */
    private const SECRET = 'unit-test-secret-unit-test-secret-unit-test-secret-unit-test-1234';

    private UserRepository&MockInterface $users;

    private RevokedTokenRepository&MockInterface $revokedTokens;

    protected function setUp(): void
    {
        parent::setUp();

        config(['auth.jwt.secret' => self::SECRET]);
        $this->users = $this->mock(UserRepository::class);
        $this->revokedTokens = $this->mock(RevokedTokenRepository::class);
    }

    private function service(): JwtAuthService
    {
        return app(JwtAuthService::class);
    }

    /** Signed exactly like the Nest api-gateway does. */
    private function token(array $overrides = [], string $secret = self::SECRET, string $algorithm = 'HS256'): string
    {
        $claims = ['sub' => 7, 'email' => 'ada@example.test', 'role' => 'admin', 'jti' => 'jti-1', 'iat' => time(), 'exp' => time() + 3600, ...$overrides];

        return JWT::encode(array_filter($claims, fn ($value) => $value !== null), $secret, $algorithm);
    }

    public function test_a_valid_token_resolves_its_user(): void
    {
        $user = User::factory()->make(['id' => 7]);
        $this->revokedTokens->shouldReceive('isRevoked')->once()->with('jti-1')->andReturnFalse();
        $this->users->shouldReceive('findById')->once()->with(7)->andReturn($user);

        $this->assertSame($user, $this->service()->userFromToken($this->token()));
    }

    public function test_tokens_that_fail_verification_never_reach_redis_or_the_database(): void
    {
        $this->revokedTokens->shouldNotReceive('isRevoked');
        $this->users->shouldNotReceive('findById');

        $tokens = [
            'missing' => null,
            'empty' => '',
            'garbage' => 'not.a.jwt',
            'wrong secret' => $this->token(secret: str_repeat('x', 64)),
            'expired' => $this->token(['iat' => time() - 7200, 'exp' => time() - 3600]),
            'other algorithm' => $this->token(algorithm: 'HS512'),
            'no jti' => $this->token(['jti' => null]),
            'non-numeric sub' => $this->token(['sub' => 'ada']),
        ];

        foreach ($tokens as $case => $token) {
            $this->assertNull($this->service()->userFromToken($token), "{$case} token was accepted");
        }
    }

    public function test_a_token_revoked_by_a_nest_logout_is_refused(): void
    {
        $this->revokedTokens->shouldReceive('isRevoked')->andReturnTrue();
        $this->users->shouldNotReceive('findById');

        $this->assertNull($this->service()->userFromToken($this->token()));
    }

    public function test_redis_being_down_does_not_lock_admins_out(): void
    {
        $user = User::factory()->make(['id' => 7]);
        $this->revokedTokens->shouldReceive('isRevoked')->andThrow(new RedisException('Connection refused'));
        $this->users->shouldReceive('findById')->andReturn($user);
        Log::shouldReceive('warning')->once()->withArgs(fn (string $message) => str_contains($message, 'Redis unavailable'));

        $this->assertSame($user, $this->service()->userFromToken($this->token()));
    }

    public function test_a_missing_or_too_short_secret_is_a_configuration_error_not_a_silent_rejection(): void
    {
        foreach ([null, 'only-31-bytes-long-secret-12345'] as $secret) {
            config(['auth.jwt.secret' => $secret]);

            try {
                $this->service()->userFromToken($this->token());
                $this->fail('A missing or short secret was not reported.');
            } catch (LogicException $e) {
                $this->assertStringContainsString('at least 32 bytes', $e->getMessage());
            }
        }
    }
}
