import 'reflect-metadata';
import { SortOrder } from '../../../common/enums/sort-order.enum.js';
import { ProductQueryDto } from '../../dto/product-query.dto.js';
import { ProductSortField } from '../../enums/product-sort-field.enum.js';
import { ProductListQuery } from '../../queries/product-list.query.js';
import type { ProductsQuery } from '../../types/product.types.js';

function recordingQuery() {
  const query = {
    andWhere: vi.fn(),
    orderBy: vi.fn(),
    addOrderBy: vi.fn(),
    offset: vi.fn(),
    limit: vi.fn(),
  };
  for (const method of Object.values(query)) {
    method.mockReturnValue(query);
  }
  return query;
}

describe('ProductListQuery', () => {
  const apply = (filters: Partial<ProductQueryDto>) => {
    const query = recordingQuery();
    new ProductListQuery(Object.assign(new ProductQueryDto(), filters)).applyTo(
      query as unknown as ProductsQuery,
    );
    return query;
  };

  it('adds no filters by default and sorts newest first', () => {
    const query = apply({});

    expect(query.andWhere).not.toHaveBeenCalled();
    expect(query.orderBy).toHaveBeenCalledWith('product.createdAt', 'DESC');
    expect(query.addOrderBy).toHaveBeenCalledWith('product.id', 'DESC');
  });

  it('searches case-insensitively and escapes LIKE wildcards', () => {
    const query = apply({ search: '50%_off' });

    expect(query.andWhere).toHaveBeenCalledWith('product.name ILIKE :search', {
      search: '%50\\%\\_off%',
    });
  });

  it('filters by category and price range', () => {
    const query = apply({ categoryId: 3, minPrice: 10, maxPrice: 20 });

    expect(query.andWhere.mock.calls).toEqual([
      ['product.categoryId = :categoryId', { categoryId: 3 }],
      ['product.price >= :minPrice', { minPrice: 10 }],
      ['product.price <= :maxPrice', { maxPrice: 20 }],
    ]);
  });

  it('maps the sort field to a column and breaks ties by id', () => {
    const query = apply({
      sortBy: ProductSortField.Price,
      sortOrder: SortOrder.Asc,
    });

    expect(query.orderBy).toHaveBeenCalledWith('product.price', 'ASC');
    expect(query.addOrderBy).toHaveBeenCalledWith('product.id', 'ASC');
  });

  it('turns page and limit into offset and limit', () => {
    const query = apply({ page: 3, limit: 25 });

    expect(query.offset).toHaveBeenCalledWith(50);
    expect(query.limit).toHaveBeenCalledWith(25);
  });
});
