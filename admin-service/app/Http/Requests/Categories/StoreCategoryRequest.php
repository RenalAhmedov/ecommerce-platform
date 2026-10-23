<?php

namespace App\Http\Requests\Categories;

use App\Http\Requests\Concerns\GeneratesSlug;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCategoryRequest extends FormRequest
{
    use GeneratesSlug;

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => $this->slugRules(),
            'description' => ['nullable', 'string', 'max:5000'],
            'parent_id' => ['nullable', 'integer', Rule::exists('categories', 'id')->withoutTrashed()],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return $this->slugMessages();
    }

    protected function prepareForValidation(): void
    {
        $this->generateSlugIfMissing();
    }
}
