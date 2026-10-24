import type { estypes } from '@elastic/elasticsearch';
import { SortOrder } from '../../common/enums/sort-order.enum.js';
import type { ProductQueryDto } from '../dto/product-query.dto.js';
import { ProductSortField } from '../enums/product-sort-field.enum.js';
import { PRODUCT_SEARCH_INDEX } from './product-search.constants.js';

export type ProductSearchFilters = ProductQueryDto & { search: string };

export function isSearch(
  filters: ProductQueryDto,
): filters is ProductSearchFilters {
  return Boolean(filters.search);
}

/**
 * A product matches through any of three routes, and the best total score
 * wins: its exact SKU, all words of its name or description (in any order,
 * typos forgiven), or the start of the words of its name (typing "wireless headph": the last word may be unfinished).
 */
export function buildProductSearchRequest(
  filters: ProductSearchFilters,
): estypes.SearchRequest {
  const { search, page, limit } = filters;

  return {
    index: PRODUCT_SEARCH_INDEX,
    from: (page - 1) * limit,
    size: limit,
    track_total_hits: true,
    _source: false,
    query: {
      bool: {
        filter: buildFilters(filters),
        minimum_should_match: 1,
        should: [
          { term: { sku: { value: search, boost: 10 } } },
          {
            multi_match: {
              query: search,
              fields: ['name^3', 'description'],
              fuzziness: 'AUTO',
              operator: 'and',
            },
          },
          {
            multi_match: {
              query: search,
              type: 'bool_prefix',
              fields: ['name', 'name._2gram', 'name._3gram'],
              operator: 'and',
              boost: 2,
            },
          },
        ],
      },
    },
    sort: buildSort(filters),
  };
}

function buildFilters({
  categoryId,
  minPrice,
  maxPrice,
}: ProductSearchFilters): estypes.QueryDslQueryContainer[] {
  const filters: estypes.QueryDslQueryContainer[] = [];

  if (categoryId !== undefined) {
    filters.push({ term: { categoryId } });
  }

  if (minPrice !== undefined || maxPrice !== undefined) {
    filters.push({ range: { price: { gte: minPrice, lte: maxPrice } } });
  }

  return filters;
}

function buildSort({
  sortBy,
  sortOrder,
}: ProductSearchFilters): estypes.SortCombinations[] {
  const order = sortOrder === SortOrder.Asc ? 'asc' : 'desc';

  switch (sortBy) {
    case ProductSortField.Relevance:
      return ['_score', { createdAt: 'desc' }, { id: 'desc' }];
    case ProductSortField.Price:
      return [{ price: order }, { id: order }];
    case ProductSortField.Name:
      return [{ nameSort: order }, { id: order }];
    case ProductSortField.CreatedAt:
      return [{ createdAt: order }, { id: order }];
  }
}
