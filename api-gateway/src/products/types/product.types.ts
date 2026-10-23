import type { SelectQueryBuilder } from 'typeorm';
import type { Product } from '../entities/product.entity.js';

export type ProductsQuery = SelectQueryBuilder<Product>;

export type NewProduct = Pick<
  Product,
  'name' | 'slug' | 'sku' | 'description' | 'price' | 'categoryId' | 'isActive'
>;

export type ProductChanges = Partial<NewProduct>;

export type PurchasableProduct = Pick<Product, 'id' | 'name' | 'price'>;

export type UniqueProductValues = Partial<Pick<Product, 'sku' | 'slug'>>;
