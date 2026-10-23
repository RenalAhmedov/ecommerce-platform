import { Injectable } from '@nestjs/common';
import { InjectRepository } from '@nestjs/typeorm';
import { type EntityManager, Repository } from 'typeorm';
import {
  acquireAdvisoryLocks,
  advisoryLockKeys,
} from '../database/advisory-locks.js';
import { PRODUCT_COLUMNS } from './constants/product-query.constants.js';
import type { ProductQueryDto } from './dto/product-query.dto.js';
import { Product } from './entities/product.entity.js';
import { ProductListQuery } from './queries/product-list.query.js';
import type {
  NewProduct,
  ProductChanges,
  ProductsQuery,
  PurchasableProduct,
  UniqueProductValues,
} from './types/product.types.js';

@Injectable()
export class ProductsRepository {
  constructor(
    @InjectRepository(Product)
    private readonly repository: Repository<Product>,
  ) {}

  withManager(manager: EntityManager): ProductsRepository {
    return new ProductsRepository(manager.getRepository(Product));
  }

  transaction<T>(
    work: (products: ProductsRepository, manager: EntityManager) => Promise<T>,
  ): Promise<T> {
    return this.repository.manager.transaction((manager) =>
      work(this.withManager(manager), manager),
    );
  }

  findPurchasableByIds(ids: number[]): Promise<PurchasableProduct[]> {
    return this.repository
      .createQueryBuilder('product')
      .select(['product.id', 'product.name', 'product.price'])
      .where('product.id IN (:...ids)', { ids })
      .andWhere('product.isActive = true')
      .getMany();
  }

  async lockById(id: number): Promise<boolean> {
    const product = await this.repository
      .createQueryBuilder('product')
      .select('product.id')
      .where('product.id = :id', { id })
      .setLock('pessimistic_write')
      .getOne();

    return product !== null;
  }

  lockUniqueValues({ sku, slug }: UniqueProductValues): Promise<void> {
    const keys = [
      sku === undefined ? null : advisoryLockKeys.productSku(sku),
      slug === undefined ? null : advisoryLockKeys.productSlug(slug),
    ].filter((key) => key !== null);

    return acquireAdvisoryLocks(this.repository, keys);
  }

  findActivePaginated(filters: ProductQueryDto): Promise<[Product[], number]> {
    return new ProductListQuery(filters)
      .applyTo(this.createActiveProductsQuery())
      .getManyAndCount();
  }

  findActiveById(id: number): Promise<Product | null> {
    return this.createActiveProductsQuery()
      .andWhere('product.id = :id', { id })
      .getOne();
  }

  getById(id: number): Promise<Product> {
    return this.createProductsQuery()
      .where('product.id = :id', { id })
      .getOneOrFail();
  }

  existsBySku(sku: string, excludeId?: number): Promise<boolean> {
    return this.existsByColumn('sku', sku, excludeId);
  }

  existsBySlug(slug: string, excludeId?: number): Promise<boolean> {
    return this.existsByColumn('slug', slug, excludeId);
  }

  create(data: NewProduct): Promise<Product> {
    return this.repository.save(this.repository.create(data));
  }

  async update(id: number, changes: ProductChanges): Promise<void> {
    await this.repository.update(id, changes);
  }

  private existsByColumn(
    column: 'sku' | 'slug',
    value: string,
    excludeId?: number,
  ): Promise<boolean> {
    const productsQuery = this.repository
      .createQueryBuilder('product')
      .withDeleted()
      .where(`product.${column} = :value`, { value });

    if (excludeId !== undefined) {
      productsQuery.andWhere('product.id <> :excludeId', { excludeId });
    }

    return productsQuery.getExists();
  }

  private createProductsQuery(): ProductsQuery {
    return this.repository
      .createQueryBuilder('product')
      .select(PRODUCT_COLUMNS)
      .leftJoin('product.category', 'category')
      .addSelect(['category.id', 'category.name'])
      .leftJoin('product.inventory', 'inventory')
      .addSelect(['inventory.id', 'inventory.quantity']);
  }

  private createActiveProductsQuery(): ProductsQuery {
    return this.createProductsQuery().where('product.isActive = true');
  }
}
