<?php

namespace App\Http\Requests\Categories;

use Illuminate\Contracts\Validation\ValidationRule;

class UpdateCategoryRequest extends StoreCategoryRequest
{
    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return collect(parent::rules())
            ->map(fn (array $rules) => ['sometimes', ...$rules])
            ->all();
    }

    protected function shouldGenerateSlug(): bool
    {
        return $this->exists('slug') && parent::shouldGenerateSlug();
    }
}
