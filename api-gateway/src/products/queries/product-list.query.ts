import { escapeLikePattern } from '../../common/utils/escape-like-pattern.js';
import { SORT_COLUMNS } from '../constants/product-query.constants.js';
import type { ProductQueryDto } from '../dto/product-query.dto.js';
import type { ProductsQuery } from '../types/product.types.js';

export class ProductListQuery {
  constructor(private readonly filters: ProductQueryDto) {}

  applyTo(productsQuery: ProductsQuery): ProductsQuery {
    this.applySearch(productsQuery);
    this.applyCategory(productsQuery);
    this.applyPriceRange(productsQuery);
    this.applySorting(productsQuery);
    this.applyPagination(productsQuery);

    return productsQuery;
  }

  private applySearch(productsQuery: ProductsQuery): void {
    const { search } = this.filters;

    if (!search) {
      return;
    }

    productsQuery.andWhere('product.name ILIKE :search', {
      search: `%${escapeLikePattern(search)}%`,
    });
  }

  private applyCategory(productsQuery: ProductsQuery): void {
    const { categoryId } = this.filters;

    if (categoryId === undefined) {
      return;
    }

    productsQuery.andWhere('product.categoryId = :categoryId', { categoryId });
  }

  private applyPriceRange(productsQuery: ProductsQuery): void {
    const { minPrice, maxPrice } = this.filters;

    if (minPrice !== undefined) {
      productsQuery.andWhere('product.price >= :minPrice', { minPrice });
    }

    if (maxPrice !== undefined) {
      productsQuery.andWhere('product.price <= :maxPrice', { maxPrice });
    }
  }

  private applySorting(productsQuery: ProductsQuery): void {
    const { sortBy, sortOrder } = this.filters;

    productsQuery
      .orderBy(SORT_COLUMNS[sortBy], sortOrder)
      .addOrderBy('product.id', sortOrder);
  }

  private applyPagination(productsQuery: ProductsQuery): void {
    const { page, limit } = this.filters;

    productsQuery.offset((page - 1) * limit).limit(limit);
  }
}
