import { Injectable } from '@nestjs/common';
import { InjectRepository } from '@nestjs/typeorm';
import { Repository } from 'typeorm';
import { InventoryMovement } from '../inventory/entities/inventory-movement.entity.js';
import { Inventory } from '../inventory/entities/inventory.entity.js';
import { InventoryMovementReason } from '../inventory/enums/inventory-movement-reason.enum.js';
import { PRODUCT_COLUMNS } from './constants/product-query.constants.js';
import type { ProductQueryDto } from './dto/product-query.dto.js';
import { Product } from './entities/product.entity.js';
import { ProductListQuery } from './queries/product-list.query.js';
import type {
  NewProduct,
  ProductChanges,
  ProductsQuery,
  UniqueProductValues,
} from './types/product.types.js';

@Injectable()
export class ProductsRepository {
  constructor(
    @InjectRepository(Product)
    private readonly repository: Repository<Product>,
  ) {}

  transaction<T>(
    work: (products: ProductsRepository) => Promise<T>,
  ): Promise<T> {
    return this.repository.manager.transaction((manager) =>
      work(new ProductsRepository(manager.getRepository(Product))),
    );
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

  async lockUniqueValues(values: UniqueProductValues): Promise<void> {
    const lockKeys = Object.entries(values)
      .filter(([, value]) => value !== undefined)
      .map(([column, value]) => `products.${column}:${value}`)
      .sort();

    for (const lockKey of lockKeys) {
      await this.repository.query(
        'SELECT pg_advisory_xact_lock(hashtext($1))',
        [lockKey],
      );
    }
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

  findById(id: number): Promise<Product | null> {
    return this.createProductsQuery()
      .where('product.id = :id', { id })
      .getOne();
  }

  existsBySku(sku: string, excludeId?: number): Promise<boolean> {
    return this.existsByColumn('sku', sku, excludeId);
  }

  existsBySlug(slug: string, excludeId?: number): Promise<boolean> {
    return this.existsByColumn('slug', slug, excludeId);
  }

  createWithInventory(
    data: NewProduct,
    initialStock: number,
    createdBy: number,
  ): Promise<Product> {
    return this.repository.manager.transaction(async (manager) => {
      const product = await manager.save(manager.create(Product, data));

      await manager.insert(Inventory, {
        productId: product.id,
        quantity: initialStock,
      });

      if (initialStock > 0) {
        await manager.insert(InventoryMovement, {
          productId: product.id,
          userId: createdBy,
          change: initialStock,
          reason: InventoryMovementReason.Initial,
        });
      }

      return product;
    });
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
