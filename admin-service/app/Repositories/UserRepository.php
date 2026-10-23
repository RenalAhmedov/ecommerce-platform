<?php

namespace App\Repositories;

use App\Models\User;

class UserRepository
{
    public function updatePassword(User $user, string $password): void
    {
        $user->update(['password' => $password]);
    }

    public function updatePasswordAndRememberToken(User $user, string $password, string $rememberToken): void
    {
        $user->forceFill([
            'password' => $password,
            'remember_token' => $rememberToken,
        ])->save();
    }
}
