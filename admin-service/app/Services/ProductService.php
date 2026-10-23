<?php

namespace App\Services;

use App\Models\Product;
use App\Models\User;
use App\Repositories\ProductRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProductService
{
    public function __construct(
        private readonly ProductRepository $products,
        private readonly InventoryService $inventory,
    ) {}

    /**
     * @param  array{search?: ?string, category_id?: int|string|null, status?: ?string}  $filters
     * @return LengthAwarePaginator<int, Product>
     */
    public function paginate(array $filters, int $perPage): LengthAwarePaginator
    {
        return $this->products->paginate($filters, $perPage);
    }

    public function withInventory(Product $product): Product
    {
        return $this->products->loadInventory($product);
    }

    /** @param  array<string, mixed>  $data */
    public function create(array $data, User $admin): Product
    {
        return DB::transaction(function () use ($data, $admin) {
            $this->products->lockUniqueValues($data['sku'], $data['slug']);
            $this->ensureAvailable($data['sku'], $data['slug']);

            $product = $this->products->create(Arr::except($data, 'initial_stock'));
            $this->inventory->initializeStock($product->id, $data['initial_stock'] ?? 0, $admin);

            return $product;
        });
    }

    /** @param  array<string, mixed>  $data */
    public function update(Product $product, array $data): Product
    {
        return DB::transaction(function () use ($product, $data) {
            $product = $this->products->lockById($product->id);
            $product->fill($data);

            $sku = $product->isDirty('sku') ? $product->sku : null;
            $slug = $product->isDirty('slug') ? $product->slug : null;

            $this->products->lockUniqueValues($sku, $slug);
            $this->ensureAvailable($sku, $slug, ignoreId: $product->id);

            $this->products->save($product);

            return $product;
        });
    }

    public function delete(Product $product): void
    {
        $this->products->delete($product);
    }

    public function restore(Product $product): void
    {
        $this->products->restore($product);
    }

    /** @throws ValidationException */
    private function ensureAvailable(?string $sku, ?string $slug, ?int $ignoreId = null): void
    {
        $errors = [];

        if ($sku !== null && $this->products->existsBySku($sku, $ignoreId)) {
            $errors['sku'] = 'A product with this sku already exists.';
        }
        if ($slug !== null && $this->products->existsBySlug($slug, $ignoreId)) {
            $errors['slug'] = 'A product with this slug already exists.';
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }
}
