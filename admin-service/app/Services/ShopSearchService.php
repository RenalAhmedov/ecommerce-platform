<?php

namespace App\Services;

use App\Integrations\NestShopClient;
use App\Integrations\NestUnavailableException;
use App\Models\Product;
use Illuminate\Support\Facades\Log;

/**
 * Shows admins what customers get when they search the shop. The search
 * (Elasticsearch: typos, relevance) lives in the api-gateway, so it is asked
 * through its GraphQL API, for exactly the fields the page shows.
 */
class ShopSearchService
{
    private const QUERY = <<<'GRAPHQL'
        query ShopSearch($search: String!, $sortBy: ProductSortField!, $sortOrder: SortOrder!, $categoryId: Int, $minPrice: Float, $maxPrice: Float) {
          products(search: $search, sortBy: $sortBy, sortOrder: $sortOrder, categoryId: $categoryId, minPrice: $minPrice, maxPrice: $maxPrice, limit: 20) {
            data { id name sku price stock imagePath category { name } }
            meta { total }
          }
        }
        GRAPHQL;

    /** The form's sort choices => the GraphQL enum names. */
    public const SORTS = ['relevance' => 'Relevance', 'price' => 'Price', 'name' => 'Name', 'newest' => 'CreatedAt'];

    public function __construct(private readonly NestShopClient $shop) {}

    /**
     * @param  array{q?: ?string, sort: string, order: string, category_id?: ?int, min_price?: ?string, max_price?: ?string}  $filters
     * @return array{available: bool, products: list<array<string, mixed>>, total: int, searchEngine: string}|null null without a search term
     */
    public function search(array $filters): ?array
    {
        if (blank($filters['q'] ?? null)) {
            return null;
        }

        try {
            $page = $this->shop->graphql(self::QUERY, $this->variables($filters))['products'];
        } catch (NestUnavailableException $e) {
            Log::warning('Shop search preview: api-gateway unavailable', ['error' => $e->getMessage()]);

            return ['available' => false, 'products' => [], 'total' => 0, 'searchEngine' => 'unknown'];
        }

        return [
            'available' => true,
            'products' => array_map($this->toRow(...), $page['data']),
            'total' => $page['meta']['total'],
            'searchEngine' => $this->searchEngine(),
        ];
    }

    /** @return array<string, mixed> */
    private function variables(array $filters): array
    {
        return [
            'search' => $filters['q'],
            'sortBy' => self::SORTS[$filters['sort']],
            'sortOrder' => $filters['order'] === 'asc' ? 'Asc' : 'Desc',
            'categoryId' => $filters['category_id'] ?? null,
            'minPrice' => isset($filters['min_price']) ? (float) $filters['min_price'] : null,
            'maxPrice' => isset($filters['max_price']) ? (float) $filters['max_price'] : null,
        ];
    }

    /**
     * @param  array{id: int, name: string, sku: string, price: string, stock: int, imagePath: ?string, category: ?array{name: string}}  $product
     * @return array<string, mixed>
     */
    private function toRow(array $product): array
    {
        return [
            ...$product,
            'category' => $product['category']['name'] ?? null,
            'thumbnailUrl' => $product['imagePath'] ? Product::thumbnailUrlFor($product['imagePath']) : null,
        ];
    }

    /** "up", or "down"/"disabled" when the shop answered from Postgres (no typo tolerance or relevance). */
    private function searchEngine(): string
    {
        try {
            return $this->shop->health()['checks']['elasticsearch'] ?? 'unknown';
        } catch (NestUnavailableException) {
            return 'unknown';
        }
    }
}
