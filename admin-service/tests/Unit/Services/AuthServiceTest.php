<?php

namespace Tests\Unit\Services;

use App\Services\AuthService;
use App\Services\UserService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class AuthServiceTest extends TestCase
{
    private AuthService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mock(UserService::class);
        $this->service = app(AuthService::class);
    }

    public function test_login_only_accepts_admins(): void
    {
        Auth::shouldReceive('guard->attempt')
            ->once()
            ->with(['email' => 'ada@example.test', 'password' => 'secret', 'role' => 'admin'], true)
            ->andReturnTrue();

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
