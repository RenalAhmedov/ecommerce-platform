<?php

namespace App\Http\Requests\Users;

use App\Validation\UserRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreUserRequest extends FormRequest
{
    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return UserRules::rules();
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('email')) {
            $this->merge(['email' => UserRules::normalizeEmail($this->input('email'))]);
        }
    }
}
