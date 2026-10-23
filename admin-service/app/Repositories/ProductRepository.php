<?php

namespace App\Repositories;

use App\Enums\ProductStatusFilter;
use App\Models\Product;
use App\Support\AdvisoryLock;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class ProductRepository
{
    /**
     * @param  array{search?: ?string, category_id?: int|string|null, status?: ?string}  $filters
     * @return LengthAwarePaginator<int, Product>
     */
    public function paginate(array $filters, int $perPage): LengthAwarePaginator
    {
        return Product::query()
            ->with(['category', 'inventory'])
            ->when($filters['search'] ?? null, function (Builder $query, string $search) {
                $pattern = '%'.addcslashes($search, '\\%_').'%';
                $query->where(fn (Builder $query) => $query->whereLike('name', $pattern)->orWhereLike('sku', $pattern));
            })
            ->when($filters['category_id'] ?? null, fn (Builder $query, $categoryId) => $query->where('category_id', (int) $categoryId))
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => match (ProductStatusFilter::from($status)) {
                ProductStatusFilter::Active => $query->where('is_active', true),
                ProductStatusFilter::Inactive => $query->where('is_active', false),
                ProductStatusFilter::Trashed => $query->onlyTrashed(),
            })
            ->latest('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function loadDetails(Product $product): Product
    {
        return $product->loadMissing(['category', 'inventory']);
    }

    /** Re-reads the row with SELECT … FOR UPDATE; must run inside a transaction. */
    public function lockById(int $id): Product
    {
        return Product::whereKey($id)->lockForUpdate()->firstOrFail();
    }

    /** Like lockById, but also finds trashed products and returns null when the row is gone. */
    public function lockByIdWithTrashed(int $id): ?Product
    {
        return Product::withTrashed()->whereKey($id)->lockForUpdate()->first();
    }

    /** Same advisory lock keys as the Nest api-gateway, so both services wait on each other. */
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

    /** Soft-deleted products count: their SKU stays reserved so a restore never duplicates it. */
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

    private function existsBy(string $column, string $value, ?int $ignoreId): bool
    {
        return Product::withTrashed()
            ->where($column, $value)
            ->when($ignoreId, fn (Builder $query) => $query->whereKeyNot($ignoreId))
            ->exists();
    }
}
