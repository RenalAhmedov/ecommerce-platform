<?php

namespace App\Repositories;

use App\Models\User;

class UserRepository
{
    /** Soft-deleted users are not found, so their tokens stop working. */
    public function findById(int $id): ?User
    {
        return User::find($id);
    }

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
