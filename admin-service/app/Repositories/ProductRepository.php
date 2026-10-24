<?php

namespace App\Repositories;

use App\Enums\ProductStatusFilter;
use App\Models\Product;
use App\Support\AdvisoryLock;
use App\Support\LikePattern;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\LazyCollection;

class ProductRepository
{
    /**
     * @param  array{search?: ?string, category_id?: int|string|null, status?: ?string}  $filters
     * @return LengthAwarePaginator<int, Product>
     */
    public function paginate(array $filters, int $perPage): LengthAwarePaginator
    {
        return $this->filtered($filters)
            ->latest('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * @param  array{search?: ?string, category_id?: int|string|null, status?: ?string}  $filters
     * @return LazyCollection<int, Product>
     */
    public function lazyFiltered(array $filters): LazyCollection
    {
        return $this->filtered($filters)->lazyById(500);
    }

    public function findBySkuWithTrashed(string $sku): ?Product
    {
        return Product::withTrashed()->where('sku', $sku)->first();
    }

    public function loadDetails(Product $product): Product
    {
        return $product->loadMissing(['category', 'inventory']);
    }

    public function lockById(int $id): Product
    {
        return Product::whereKey($id)->lockForUpdate()->firstOrFail();
    }

    public function lockByIdWithTrashed(int $id): ?Product
    {
        return Product::withTrashed()->whereKey($id)->lockForUpdate()->first();
    }

    public function lockUniqueValues(?string $sku, ?string $slug): void
    {
        $keys = [];
        if ($sku !== null) {
            $keys[] = AdvisoryLock::productSku($sku);
        }
        if ($slug !== null) {
            $keys[] = AdvisoryLock::productSlug($slug);
        }

        AdvisoryLock::acquire($keys);
    }

    // Deleted products keep their SKU reserved, so a restore can't create a duplicate.
    public function existsBySku(string $sku, ?int $ignoreId = null): bool
    {
        return $this->existsBy('sku', $sku, $ignoreId);
    }

    public function existsBySlug(string $slug, ?int $ignoreId = null): bool
    {
        return $this->existsBy('slug', $slug, $ignoreId);
    }

    /** @param  array<string, mixed>  $attributes */
    public function create(array $attributes): Product
    {
        return Product::create($attributes);
    }

    public function save(Product $product): void
    {
        $product->save();
    }

    public function delete(Product $product): void
    {
        $product->delete();
    }

    public function restore(Product $product): void
    {
        $product->restore();
    }

    /**
     * @param  array{search?: ?string, category_id?: int|string|null, status?: ?string}  $filters
     * @return Builder<Product>
     */
    private function filtered(array $filters): Builder
    {
        return Product::query()
            ->with(['category', 'inventory'])
            ->when($filters['search'] ?? null, function (Builder $query, string $search) {
                $pattern = LikePattern::contains($search);
                $query->where(fn (Builder $query) => $query->whereLike('name', $pattern)->orWhereLike('sku', $pattern));
            })
            ->when($filters['category_id'] ?? null, fn (Builder $query, $categoryId) => $query->where('category_id', (int) $categoryId))
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => match (ProductStatusFilter::from($status)) {
                ProductStatusFilter::Active => $query->where('is_active', true),
                ProductStatusFilter::Inactive => $query->where('is_active', false),
                ProductStatusFilter::Trashed => $query->onlyTrashed(),
            });
    }

    private function existsBy(string $column, string $value, ?int $ignoreId): bool
    {
        return Product::withTrashed()
            ->where($column, $value)
            ->when($ignoreId, fn (Builder $query) => $query->whereKeyNot($ignoreId))
            ->exists();
    }
}
