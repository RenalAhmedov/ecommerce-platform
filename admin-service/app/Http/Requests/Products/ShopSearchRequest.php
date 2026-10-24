<?php

namespace App\Http\Requests\Products;

use App\Services\ShopSearchService;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ShopSearchRequest extends FormRequest
{
    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:100'],
            'sort' => ['nullable', Rule::in(array_keys(ShopSearchService::SORTS))],
            'order' => ['nullable', Rule::in(['asc', 'desc'])],
            'category_id' => ['nullable', 'integer', 'min:1'],
            'min_price' => ['nullable', 'numeric', 'min:0'],
            'max_price' => ['nullable', 'numeric', 'min:0', 'gte:min_price'],
        ];
    }

    /** @return array{q: ?string, sort: string, order: string, category_id: ?int, min_price: ?string, max_price: ?string} */
    public function filters(): array
    {
        return [
            'q' => $this->string('q')->trim()->value() ?: null,
            'sort' => $this->input('sort') ?? 'relevance',
            'order' => $this->input('order') ?? 'desc',
            'category_id' => $this->filled('category_id') ? $this->integer('category_id') : null,
            'min_price' => $this->input('min_price'),
            'max_price' => $this->input('max_price'),
        ];
    }
}
