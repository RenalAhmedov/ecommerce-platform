import { Injectable } from '@nestjs/common';
import { InjectRepository } from '@nestjs/typeorm';
import { Repository, type SelectQueryBuilder } from 'typeorm';
import type { ProductQueryDto } from './dto/product-query.dto.js';
import { Product } from './entities/product.entity.js';
import { ProductSortField } from './enums/product-sort-field.enum.js';

type ProductsQuery = SelectQueryBuilder<Product>;

const PRODUCT_COLUMNS = [
  'product.id',
  'product.name',
  'product.slug',
  'product.sku',
  'product.description',
  'product.price',
  'product.imagePath',
  'product.createdAt',
  'product.updatedAt',
];

const SORT_COLUMNS: Record<ProductSortField, string> = {
  [ProductSortField.CreatedAt]: 'product.createdAt',
  [ProductSortField.Price]: 'product.price',
  [ProductSortField.Name]: 'product.name',
};

@Injectable()
export class ProductsRepository {
  constructor(
    @InjectRepository(Product)
    private readonly repository: Repository<Product>,
  ) {}

  findActivePaginated(filters: ProductQueryDto): Promise<[Product[], number]> {
    const productsQuery = this.createActiveProductsQuery();

    this.applySearch(productsQuery, filters.search);
    this.applyCategory(productsQuery, filters.categoryId);
    this.applyPriceRange(productsQuery, filters.minPrice, filters.maxPrice);
    this.applySorting(productsQuery, filters);
    this.applyPagination(productsQuery, filters);

    return productsQuery.getManyAndCount();
  }

  findActiveById(id: number): Promise<Product | null> {
    return this.createActiveProductsQuery()
      .andWhere('product.id = :id', { id })
      .getOne();
  }

  private createActiveProductsQuery(): ProductsQuery {
    return this.repository
      .createQueryBuilder('product')
      .select(PRODUCT_COLUMNS)
      .leftJoin('product.category', 'category')
      .addSelect(['category.id', 'category.name'])
      .leftJoin('product.inventory', 'inventory')
      .addSelect(['inventory.id', 'inventory.quantity'])
      .where('product.isActive = true');
  }

  private applySearch(productsQuery: ProductsQuery, search?: string): void {
    if (!search) {
      return;
    }

    productsQuery.andWhere('product.name ILIKE :search', {
      search: `%${escapeLikePattern(search)}%`,
    });
  }

  private applyCategory(
    productsQuery: ProductsQuery,
    categoryId?: number,
  ): void {
    if (categoryId === undefined) {
      return;
    }

    productsQuery.andWhere('product.categoryId = :categoryId', { categoryId });
  }

  private applyPriceRange(
    productsQuery: ProductsQuery,
    minPrice?: number,
    maxPrice?: number,
  ): void {
    if (minPrice !== undefined) {
      productsQuery.andWhere('product.price >= :minPrice', { minPrice });
    }

    if (maxPrice !== undefined) {
      productsQuery.andWhere('product.price <= :maxPrice', { maxPrice });
    }
  }

  private applySorting(
    productsQuery: ProductsQuery,
    { sortBy, sortOrder }: ProductQueryDto,
  ): void {
    productsQuery
      .orderBy(SORT_COLUMNS[sortBy], sortOrder)
      .addOrderBy('product.id', sortOrder);
  }

  private applyPagination(
    productsQuery: ProductsQuery,
    { page, limit }: ProductQueryDto,
  ): void {
    productsQuery.offset((page - 1) * limit).limit(limit);
  }
}

function escapeLikePattern(value: string): string {
  return value.replace(/[\\%_]/g, '\\$&');
}
