<?php

namespace App\Http\Requests\Products;

use App\Http\Requests\Concerns\GeneratesSlug;
use App\Validation\ProductRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class StoreProductRequest extends FormRequest
{
    use GeneratesSlug;

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return ProductRules::rules();
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ProductRules::messages();
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('sku'))) {
            $this->merge(['sku' => Str::upper($this->input('sku'))]);
        }

        $this->generateSlugIfMissing();
    }
}
