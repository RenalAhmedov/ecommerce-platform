<?php

namespace Tests\Unit\Services;

use App\Models\User;
use App\Services\AuthService;
use App\Services\JwtAuthService;
use App\Services\UserService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\ValidationException;
use Mockery\MockInterface;
use RedisException;
use Tests\Unit\UnitTestCase;

class AuthServiceTest extends UnitTestCase
{
    private const TOKEN_CLAIMS = ['jti' => 'jti-1', 'sub' => 7, 'iat' => 1_000, 'exp' => 4_600];

    private UserService&MockInterface $users;

    private JwtAuthService&MockInterface $tokens;

    private AuthService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->users = $this->mock(UserService::class);
        $this->tokens = $this->mock(JwtAuthService::class);
        $this->service = app(AuthService::class);
    }

    /** Matches the claims object rebuilt from the session (Mockery compares objects by identity). */
    private function theSessionClaims(object $claims): bool
    {
        return (array) $claims === self::TOKEN_CLAIMS;
    }

    public function test_login_only_accepts_admins(): void
    {
        Auth::shouldReceive('guard->attempt')
            ->once()
            ->with(['email' => 'ada@example.test', 'password' => 'secret', 'role' => 'admin'], true)
            ->andReturnTrue();
        Auth::shouldReceive('guard->id')->andReturn(5);

        $this->service->login('ada@example.test', 'secret', true, '1.2.3.4');
    }

    public function test_a_failed_login_counts_towards_the_lockout(): void
    {
        Auth::shouldReceive('guard->attempt')->andReturnFalse();

        $this->expectException(ValidationException::class);

        try {
            $this->service->login('Ada@Example.test', 'wrong', false, '1.2.3.4');
        } finally {
            $this->assertSame(1, RateLimiter::attempts('ada@example.test|1.2.3.4'));
        }
    }

    public function test_a_token_login_opens_the_panel_and_remembers_only_the_claims(): void
    {
        $admin = User::factory()->admin()->make(['id' => 7]);
        $this->tokens->shouldReceive('verify')->once()->with('the-token')->andReturn((object) self::TOKEN_CLAIMS);
        $this->users->shouldReceive('find')->once()->with(7)->andReturn($admin);
        Auth::shouldReceive('guard->login')->once()->with($admin);

        $this->service->loginWithToken('the-token', '1.2.3.4');

        $this->assertSame(self::TOKEN_CLAIMS, Session::get('auth.access_token'));
    }

    public function test_bad_revoked_and_customer_tokens_get_the_same_refusal(): void
    {
        $this->tokens->shouldReceive('verify')->with('bad')->andReturnNull();
        $this->tokens->shouldReceive('verify')->with('customer')->andReturn((object) self::TOKEN_CLAIMS);
        $this->users->shouldReceive('find')->with(7)->andReturn(User::factory()->make(['id' => 7]));
        Auth::shouldReceive('guard->login')->never();

        foreach (['bad', 'customer'] as $token) {
            try {
                $this->service->loginWithToken($token, '1.2.3.4');
                $this->fail("The {$token} token opened the panel.");
            } catch (ValidationException $e) {
                $this->assertSame(['token' => ['This access token cannot open the admin panel.']], $e->errors());
            }
        }
    }

    public function test_a_session_opened_with_a_token_ends_with_it(): void
    {
        Session::put('auth.access_token', self::TOKEN_CLAIMS);
        $this->tokens->shouldReceive('isActive')->withArgs($this->theSessionClaims(...))->andReturn(true, false);
        Auth::shouldReceive('guard->id')->andReturn(7);
        Auth::shouldReceive('guard->logout')->once();

        $this->assertFalse($this->service->endSessionIfTokenEnded());
        $this->assertTrue($this->service->endSessionIfTokenEnded());
    }

    public function test_a_password_session_never_checks_a_token(): void
    {
        $this->tokens->shouldNotReceive('isActive');

        $this->assertFalse($this->service->endSessionIfTokenEnded());
    }

    public function test_logging_out_of_a_token_session_revokes_the_token_even_if_redis_fails(): void
    {
        Session::put('auth.access_token', self::TOKEN_CLAIMS);
        $this->tokens->shouldReceive('revoke')->once()->withArgs($this->theSessionClaims(...))->andThrow(new RedisException('Connection refused'));
        Auth::shouldReceive('guard->id')->andReturn(7);
        Auth::shouldReceive('guard->logout')->once();

        $this->service->logout();
    }

    public function test_reset_links_are_only_requested_for_admins(): void
    {
        Password::shouldReceive('sendResetLink')->once()->with(['email' => 'ada@example.test', 'role' => 'admin']);

        $this->service->sendResetLink('ada@example.test');
    }

    public function test_an_invalid_reset_token_is_reported_as_a_validation_error(): void
    {
        Password::shouldReceive('reset')->once()->andReturn(Password::INVALID_TOKEN);

        $this->expectException(ValidationException::class);

        $this->service->resetPassword([
            'email' => 'ada@example.test',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
            'token' => 'bad',
        ]);
    }
}
