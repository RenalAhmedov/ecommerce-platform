<?php

namespace App\Http\Requests\Products;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/** Same rules as the Nest CreateProductDto, so both services accept the same products. */
class StoreProductRequest extends FormRequest
{
    private const SLUG_PATTERN = '/^[a-z0-9]+(?:-[a-z0-9]+)*$/';

    private const SKU_PATTERN = '/^[A-Z0-9][A-Z0-9_-]*$/';

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'regex:'.self::SLUG_PATTERN],
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
            'slug.required' => 'Could not generate a slug from the name, please provide one.',
            'slug.regex' => 'The slug may only contain lowercase letters, numbers and single hyphens.',
            'sku.regex' => 'The SKU may only contain letters, numbers, hyphens and underscores.',
        ];
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('sku'))) {
            $this->merge(['sku' => Str::upper($this->input('sku'))]);
        }

        if ($this->shouldGenerateSlug()) {
            $this->merge(['slug' => Str::slug((string) $this->input('name'))]);
        }
    }

    protected function shouldGenerateSlug(): bool
    {
        return blank($this->input('slug'));
    }
}
