<?php

namespace App\Validation;

use Illuminate\Validation\Rule;

/**
 * Product validation shared by the form/API requests and the CSV import, and
 * equal to the Nest CreateProductDto, so every entry point accepts the same products.
 */
final class ProductRules
{
    private const SKU_PATTERN = '/^[A-Z0-9][A-Z0-9_-]*$/';

    /** @return array<string, array<mixed>> */
    public static function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => SlugRules::rules(),
            'sku' => ['required', 'string', 'max:64', 'regex:'.self::SKU_PATTERN],
            'description' => ['nullable', 'string', 'max:5000'],
            'price' => ['required', 'numeric', 'decimal:0,2', 'min:0', 'max:9999999999.99'],
            'category_id' => ['nullable', 'integer', Rule::exists('categories', 'id')->withoutTrashed()],
            'is_active' => ['boolean'],
            'initial_stock' => ['nullable', 'integer', 'min:0', 'max:1000000'],
        ];
    }

    /** @return array<string, string> */
    public static function messages(): array
    {
        return [
            ...SlugRules::messages(),
            'sku.regex' => 'The SKU may only contain letters, numbers, hyphens and underscores.',
        ];
    }
}
