<?php

namespace App\Http\Requests\Products;

use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Every field is optional ("sometimes"), so a partial update only touches what
 * was sent. Stock is not editable here; it changes through stock movements.
 */
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

    /** A cleared slug field is regenerated from the name; a missing one is left alone. */
    protected function shouldGenerateSlug(): bool
    {
        return $this->exists('slug') && parent::shouldGenerateSlug();
    }
}
