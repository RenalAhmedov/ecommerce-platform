<?php

namespace App\Validation;

use App\Enums\UserRole;
use Illuminate\Validation\Rule;

final class UserRules
{
    /** @return array<string, array<mixed>> */
    public static function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255'],
            // bcrypt only uses the first 72 bytes, so longer passwords are refused rather than silently cut.
            'password' => ['required', 'string', 'min:8', 'max:72'],
            'role' => ['required', Rule::enum(UserRole::class)],
        ];
    }

    public static function normalizeEmail(mixed $email): mixed
    {
        return is_string($email) ? mb_strtolower(trim($email)) : $email;
    }
}
