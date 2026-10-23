<?php

namespace App\Services;

use App\Models\User;
use App\Repositories\UserRepository;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Str;

class UserService
{
    public function __construct(private readonly UserRepository $users) {}

    public function changePassword(User $user, string $password): void
    {
        $this->users->updatePassword($user, $password);
    }

    /** A new remember token signs the user out of "remember me" sessions on other devices. */
    public function resetPassword(User $user, string $password): void
    {
        $this->users->updatePasswordAndRememberToken($user, $password, Str::random(60));

        event(new PasswordReset($user));
    }
}
