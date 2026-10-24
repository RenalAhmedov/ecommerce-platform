import type { Product } from '../entities/product.entity.js';

/** What the index holds: only what is searched, filtered or sorted on. Prices and stock are always read from Postgres. */
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

/** Only active, not deleted products are searchable. */
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
