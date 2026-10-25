<?php

namespace App\Http\Requests\Products;

use Illuminate\Contracts\Validation\ValidationRule;

class UpdateProductRequest extends StoreProductRequest
{
    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return collect(parent::rules())
            ->except('initial_stock')
            ->map(fn (array $rules) => ['sometimes', ...$rules])
            ->all();
    }

    protected function shouldGenerateSlug(): bool
    {
        return $this->exists('slug') && parent::shouldGenerateSlug();
    }
}
