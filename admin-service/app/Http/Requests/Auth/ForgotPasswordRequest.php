<?php

namespace App\Http\Requests\Auth;

use App\Validation\UserRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ForgotPasswordRequest extends FormRequest
{
    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'email' => ['required', 'email'],
        ];
    }

    /** Emails are stored lower-cased (as the Nest service does), so "Ada@Example.com" still finds the account. */
    protected function prepareForValidation(): void
    {
        if ($this->has('email')) {
            $this->merge(['email' => UserRules::normalizeEmail($this->input('email'))]);
        }
    }
}
