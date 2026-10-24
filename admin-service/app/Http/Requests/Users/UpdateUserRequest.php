<?php

namespace App\Http\Requests\Users;

use App\Validation\UserRules;
use Illuminate\Contracts\Validation\ValidationRule;

class UpdateUserRequest extends StoreUserRequest
{
    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        $rules = collect(UserRules::rules())->map(fn (array $rules) => ['sometimes', ...$rules]);
        $rules['password'] = ['nullable', 'string', 'min:8', 'max:72'];

        return $rules->all();
    }
}
