import type { Product } from '../entities/product.entity.js';

export interface ProductSearchDocument {
  id: number;
  name: string;
  sku: string;
  description: string | null;
  categoryId: number | null;
  price: number;
  createdAt: string;
}

export type IndexableProduct = Pick<
  Product,
  | 'id'
  | 'name'
  | 'sku'
  | 'description'
  | 'categoryId'
  | 'price'
  | 'isActive'
  | 'createdAt'
  | 'deletedAt'
>;

export function isSearchable(product: IndexableProduct): boolean {
  return product.isActive && product.deletedAt === null;
}

export function toSearchDocument(
  product: IndexableProduct,
): ProductSearchDocument {
  return {
    id: product.id,
    name: product.name,
    sku: product.sku,
    description: product.description,
    categoryId: product.categoryId,
    price: Number(product.price),
    createdAt: product.createdAt.toISOString(),
  };
}
