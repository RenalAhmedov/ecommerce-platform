import { Injectable } from '@nestjs/common';
import type { EntityManager } from 'typeorm';
import { ProductsRepository } from '../products.repository.js';
import type { PurchasableProduct } from '../types/product.types.js';

@Injectable()
export class ProductCatalogService {
  constructor(private readonly productsRepository: ProductsRepository) {}

  findPurchasable(
    manager: EntityManager,
    ids: number[],
  ): Promise<PurchasableProduct[]> {
    return this.productsRepository
      .withManager(manager)
      .findPurchasableByIds(ids);
  }
}
