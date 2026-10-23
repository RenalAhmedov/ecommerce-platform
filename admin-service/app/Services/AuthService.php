<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Only admins may use the panel. Customers get exactly the same replies as
 * unknown emails, so the login and reset forms never reveal who has an account.
 */
class AuthService
{
    private const MAX_LOGIN_ATTEMPTS = 5;

    public function __construct(private readonly UserService $users) {}

    /** @throws ValidationException */
    public function login(string $email, string $password, bool $remember, string $ip): void
    {
        $throttleKey = Str::transliterate(Str::lower($email).'|'.$ip);

        if (RateLimiter::tooManyAttempts($throttleKey, self::MAX_LOGIN_ATTEMPTS)) {
            $seconds = RateLimiter::availableIn($throttleKey);

            throw ValidationException::withMessages([
                'email' => trans('auth.throttle', ['seconds' => $seconds, 'minutes' => ceil($seconds / 60)]),
            ]);
        }

        $credentials = ['email' => $email, 'password' => $password, 'role' => UserRole::Admin->value];

        if (! Auth::guard('web')->attempt($credentials, $remember)) {
            RateLimiter::hit($throttleKey);

            throw ValidationException::withMessages(['email' => trans('auth.failed')]);
        }

        RateLimiter::clear($throttleKey);
    }

    public function logout(): void
    {
        Auth::guard('web')->logout();
    }

    public function sendResetLink(string $email): void
    {
        Password::sendResetLink(['email' => $email, 'role' => UserRole::Admin->value]);
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
