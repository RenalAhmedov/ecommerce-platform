<?php

namespace App\Services;

use App\Models\Product;
use App\Models\User;
use App\Repositories\ProductRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class ProductService
{
    public function __construct(
        private readonly ProductRepository $products,
        private readonly InventoryService $inventory,
        private readonly CatalogNotifier $catalog,
    ) {}

    /**
     * @param  array{search?: ?string, category_id?: int|string|null, status?: ?string}  $filters
     * @return LengthAwarePaginator<int, Product>
     */
    public function paginate(array $filters, int $perPage): LengthAwarePaginator
    {
        return $this->products->paginate($filters, $perPage);
    }

    public function withDetails(Product $product): Product
    {
        return $this->products->loadDetails($product);
    }

    /** Includes deleted products: their SKU stays reserved. */
    public function findBySkuWithTrashed(string $sku): ?Product
    {
        return $this->products->findBySkuWithTrashed($sku);
    }

    /** @param  array<string, mixed>  $data */
    public function create(array $data, ?User $admin): Product
    {
        $product = DB::transaction(function () use ($data, $admin) {
            $this->products->lockUniqueValues($data['sku'], $data['slug']);
            $this->ensureAvailable($data['sku'], $data['slug']);

            $product = $this->products->create(Arr::except($data, 'initial_stock'));
            $this->inventory->initializeStock($product->id, $data['initial_stock'] ?? 0, $admin);

            Log::info('Product created', ['product_id' => $product->id, 'sku' => $product->sku, 'initial_stock' => $data['initial_stock'] ?? 0]);

            return $product;
        });
        $this->catalog->catalogChanged('product created');

        return $product;
    }

    /** @param  array<string, mixed>  $data */
    public function update(Product $product, array $data): Product
    {
        [$product, $changed] = DB::transaction(function () use ($product, $data) {
            $product = $this->products->lockById($product->id);
            $product->fill($data);

            $sku = $product->isDirty('sku') ? $product->sku : null;
            $slug = $product->isDirty('slug') ? $product->slug : null;

            $this->products->lockUniqueValues($sku, $slug);
            $this->ensureAvailable($sku, $slug, ignoreId: $product->id);

            $changed = array_keys($product->getDirty());
            $this->products->save($product);

            if ($changed !== []) {
                Log::info('Product updated', ['product_id' => $product->id, 'sku' => $product->sku, 'changed' => $changed]);
            }

            return [$product, $changed];
        });

        if ($changed !== []) {
            $this->catalog->catalogChanged('product updated');
        }

        return $product;
    }

    public function delete(Product $product): void
    {
        $this->products->delete($product);

        Log::info('Product moved to trash', ['product_id' => $product->id, 'sku' => $product->sku]);
        $this->catalog->catalogChanged('product deleted');
    }

    public function restore(Product $product): void
    {
        $this->products->restore($product);

        Log::info('Product restored', ['product_id' => $product->id, 'sku' => $product->sku]);
        $this->catalog->catalogChanged('product restored');
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
