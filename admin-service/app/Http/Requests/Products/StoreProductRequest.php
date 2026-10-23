<?php

namespace App\Http\Requests\Products;

use App\Http\Requests\Concerns\GeneratesSlug;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/** Same rules as the Nest CreateProductDto, so both services accept the same products. */
class StoreProductRequest extends FormRequest
{
    use GeneratesSlug;

    private const SKU_PATTERN = '/^[A-Z0-9][A-Z0-9_-]*$/';

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => $this->slugRules(),
            'sku' => ['required', 'string', 'max:64', 'regex:'.self::SKU_PATTERN],
            'description' => ['nullable', 'string', 'max:5000'],
            'price' => ['required', 'numeric', 'decimal:0,2', 'min:0', 'max:9999999999.99'],
            'category_id' => ['nullable', 'integer', Rule::exists('categories', 'id')->withoutTrashed()],
            'is_active' => ['boolean'],
            'initial_stock' => ['nullable', 'integer', 'min:0', 'max:1000000'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            ...$this->slugMessages(),
            'sku.regex' => 'The SKU may only contain letters, numbers, hyphens and underscores.',
        ];
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('sku'))) {
            $this->merge(['sku' => Str::upper($this->input('sku'))]);
        }

        $this->generateSlugIfMissing();
    }
}
