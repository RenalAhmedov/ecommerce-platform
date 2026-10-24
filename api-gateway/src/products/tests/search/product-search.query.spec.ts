import { SortOrder } from '../../../common/enums/sort-order.enum.js';
import { ProductQueryDto } from '../../dto/product-query.dto.js';
import { ProductSortField } from '../../enums/product-sort-field.enum.js';
import {
  buildProductSearchRequest,
  type ProductSearchFilters,
} from '../../search/product-search.query.js';

describe('buildProductSearchRequest', () => {
  const filters = (overrides: Partial<ProductSearchFilters> = {}) =>
    Object.assign(new ProductQueryDto(), { search: 'blue mug' }, overrides);

  it('matches the exact SKU, typo-tolerant words, or the start of words', () => {
    const { query } = buildProductSearchRequest(filters());

    expect(query?.bool?.should).toEqual([
      { term: { sku: { value: 'blue mug', boost: 10 } } },
      expect.objectContaining({
        multi_match: expect.objectContaining({
          fields: ['name^3', 'description'],
          fuzziness: 'AUTO',
          operator: 'and',
        }),
      }),
      expect.objectContaining({
        multi_match: expect.objectContaining({ type: 'bool_prefix' }),
      }),
    ]);
    expect(query?.bool?.minimum_should_match).toBe(1);
  });

  it('filters by category and price range and pages like the REST API', () => {
    const request = buildProductSearchRequest(
      filters({ categoryId: 3, minPrice: 5, maxPrice: 20, page: 3, limit: 10 }),
    );

    expect(request.query?.bool?.filter).toEqual([
      { term: { categoryId: 3 } },
      { range: { price: { gte: 5, lte: 20 } } },
    ]);
    expect([request.from, request.size]).toEqual([20, 10]);
  });

  it.each([
    [
      ProductSortField.Relevance,
      SortOrder.Asc,
      ['_score', { createdAt: 'desc' }, { id: 'desc' }],
    ],
    [ProductSortField.Price, SortOrder.Asc, [{ price: 'asc' }, { id: 'asc' }]],
    [
      ProductSortField.Name,
      SortOrder.Desc,
      [{ nameSort: 'desc' }, { id: 'desc' }],
    ],
    [
      ProductSortField.CreatedAt,
      SortOrder.Desc,
      [{ createdAt: 'desc' }, { id: 'desc' }],
    ],
  ])('sorts by %s %s', (sortBy, sortOrder, expected) => {
    expect(
      buildProductSearchRequest(filters({ sortBy, sortOrder })).sort,
    ).toEqual(expected);
  });
});
