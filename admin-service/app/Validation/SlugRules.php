<?php

namespace App\Validation;

final class SlugRules
{
    /** @return list<string> */
    public static function rules(): array
    {
        return ['required', 'string', 'max:255', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'];
    }

    /** @return array<string, string> */
    public static function messages(): array
    {
        return [
            'slug.required' => 'Could not generate a slug from the name, please provide one.',
            'slug.regex' => 'The slug may only contain lowercase letters, numbers and single hyphens.',
        ];
    }
}
