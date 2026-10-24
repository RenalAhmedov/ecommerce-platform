<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RedisException;

class AuthService
{
    private const MAX_LOGIN_ATTEMPTS = 5;

    private const TOKEN_SESSION_KEY = 'auth.access_token';

    public function __construct(
        private readonly UserService $users,
        private readonly JwtAuthService $tokens,
    ) {}

    /** @throws ValidationException */
    public function login(string $email, string $password, bool $remember, string $ip): void
    {
        $throttleKey = Str::transliterate(Str::lower($email).'|'.$ip);

        if (RateLimiter::tooManyAttempts($throttleKey, self::MAX_LOGIN_ATTEMPTS)) {
            $seconds = RateLimiter::availableIn($throttleKey);
            Log::warning('Admin login locked out after too many attempts', ['email' => $email, 'ip' => $ip, 'retry_after_seconds' => $seconds]);

            throw ValidationException::withMessages([
                'email' => trans('auth.throttle', ['seconds' => $seconds, 'minutes' => ceil($seconds / 60)]),
            ]);
        }

        $credentials = ['email' => $email, 'password' => $password, 'role' => UserRole::Admin->value];

        if (! Auth::guard('web')->attempt($credentials, $remember)) {
            RateLimiter::hit($throttleKey);
            Log::warning('Admin login failed', ['email' => $email, 'ip' => $ip]);

            throw ValidationException::withMessages(['email' => trans('auth.failed')]);
        }

        RateLimiter::clear($throttleKey);
        Log::info('Admin logged in', ['user_id' => Auth::guard('web')->id(), 'ip' => $ip]);
    }

    /** @throws ValidationException */
    public function loginWithToken(string $token, string $ip): void
    {
        $claims = $this->tokens->verify($token);
        $user = $claims === null ? null : $this->users->find((int) $claims->sub);

        // One reply for bad, revoked and customer tokens, like the password form.
        if (! $user?->isAdmin()) {
            Log::warning('Admin token login refused', ['sub' => $claims?->sub, 'ip' => $ip]);

            throw ValidationException::withMessages(['token' => __('This access token cannot open the admin panel.')]);
        }

        Auth::guard('web')->login($user);
        Session::put(self::TOKEN_SESSION_KEY, ['jti' => $claims->jti, 'sub' => $claims->sub, 'iat' => $claims->iat, 'exp' => $claims->exp]);

        Log::info('Admin logged in with an api-gateway token', ['user_id' => $user->id, 'jti' => $claims->jti, 'ip' => $ip]);
    }

    public function endSessionIfTokenEnded(): bool
    {
        $claims = Session::get(self::TOKEN_SESSION_KEY);

        if ($claims === null || $this->tokens->isActive((object) $claims)) {
            return false;
        }

        Log::info('Panel session ended with its access token', ['user_id' => Auth::guard('web')->id(), 'jti' => $claims['jti']]);
        Auth::guard('web')->logout();

        return true;
    }

    public function logout(): void
    {
        $userId = Auth::guard('web')->id();
        $claims = Session::get(self::TOKEN_SESSION_KEY);

        if ($claims !== null) {
            try {
                $this->tokens->revoke((object) $claims);
            } catch (RedisException $e) {
                // Leaving the panel must always work; the token still dies when it expires.
                Log::warning('Panel logout could not revoke its access token: Redis unavailable.', ['jti' => $claims['jti'], 'error' => $e->getMessage()]);
            }
        }

        Auth::guard('web')->logout();

        Log::info('Admin logged out', ['user_id' => $userId]);
    }

    public function sendResetLink(string $email): void
    {
        $status = Password::sendResetLink(['email' => $email, 'role' => UserRole::Admin->value]);

        Log::info('Password reset link requested', ['email' => $email, 'outcome' => $status]);
    }

    /**
     * @param  array{email: string, password: string, password_confirmation: string, token: string}  $credentials
     *
     * @throws ValidationException
     */
    public function resetPassword(array $credentials): void
    {
        $status = Password::reset(
            [...$credentials, 'role' => UserRole::Admin->value],
            fn (User $user, string $password) => $this->users->resetPassword($user, $password),
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages(['email' => __($status)]);
        }
    }
}
