import { ProductSortField } from '../enums/product-sort-field.enum.js';

export const PRODUCT_COLUMNS = [
  'product.id',
  'product.name',
  'product.slug',
  'product.sku',
  'product.description',
  'product.price',
  'product.imagePath',
  'product.isActive',
  'product.createdAt',
  'product.updatedAt',
];

export const SORT_COLUMNS: Record<ProductSortField, string> = {
  [ProductSortField.CreatedAt]: 'product.createdAt',
  [ProductSortField.Price]: 'product.price',
  [ProductSortField.Name]: 'product.name',
  // Postgres has no relevance score; Elasticsearch does (products/search).
  [ProductSortField.Relevance]: 'product.createdAt',
};
