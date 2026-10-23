<?php

namespace App\Services;

use App\Models\User;
use App\Validation\ProductRules;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Turns one CSV row into a product, matched by SKU: a new SKU creates it (with
 * "stock" as opening stock), a known SKU updates it (stock is left alone; it
 * changes only through stock movements). Rows are validated with the same
 * rules as the forms and saved through ProductService, so they get the same
 * locks and uniqueness checks.
 */
class ProductRowImporter
{
    public function __construct(private readonly ProductService $products) {}

    /**
     * @param  array<string, string>  $row  cells keyed by column name
     * @param  array<string, int>  $categoryIds  slug => id of active categories
     * @return bool true when a product was created, false when one was updated
     *
     * @throws ValidationException with the reason the row was rejected
     */
    public function import(array $row, array $categoryIds, ?User $admin): bool
    {
        $data = $this->toProductData($row, $categoryIds);
        $existing = $this->products->findBySkuWithTrashed($data['sku']);

        if ($existing?->trashed()) {
            throw ValidationException::withMessages(['sku' => 'This SKU belongs to a deleted product. Restore it first.']);
        }

        if ($existing !== null) {
            $changes = Arr::except($data, 'initial_stock');
            if ($changes['slug'] === '') {
                unset($changes['slug']);
            }
            $rules = Arr::only(ProductRules::rules(), array_keys($changes));
            $this->products->update($existing, $this->validate($changes, $rules));

            return false;
        }

        $data['slug'] = $data['slug'] !== '' ? $data['slug'] : Str::slug($data['name']);
        $this->products->create($this->validate($data, ProductRules::rules()), $admin);

        return true;
    }

    /**
     * Empty optional cells mean "not given", except category_slug: an empty
     * one in a file that has the column removes the category.
     *
     * @param  array<string, string>  $row
     * @param  array<string, int>  $categoryIds
     * @return array<string, mixed>
     *
     * @throws ValidationException for an unknown category slug
     */
    private function toProductData(array $row, array $categoryIds): array
    {
        $data = [
            'sku' => Str::upper($row['sku'] ?? ''),
            'name' => $row['name'] ?? '',
            'slug' => $row['slug'] ?? '',
            'price' => $row['price'] ?? '',
            'description' => ($row['description'] ?? '') !== '' ? $row['description'] : null,
        ];

        $categorySlug = $row['category_slug'] ?? '';
        if ($categorySlug !== '') {
            $data['category_id'] = $categoryIds[$categorySlug]
                ?? throw ValidationException::withMessages(['category_slug' => "Unknown category \"{$categorySlug}\"."]);
        } elseif (array_key_exists('category_slug', $row)) {
            $data['category_id'] = null;
        }

        if (($row['is_active'] ?? '') !== '') {
            $data['is_active'] = filter_var($row['is_active'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? $row['is_active'];
        }
        if (($row['stock'] ?? '') !== '') {
            $data['initial_stock'] = $row['stock'];
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, array<mixed>>  $rules
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    private function validate(array $data, array $rules): array
    {
        return Validator::make($data, $rules, ProductRules::messages())->validate();
    }
}
