import type { Product } from '../entities/product.entity.js';

export class ProductResponseDto {
  id: number;
  name: string;
  slug: string;
  sku: string;
  description: string | null;
  price: string;
  imagePath: string | null;
  isActive: boolean;
  category: { id: number; name: string } | null;
  stock: number;
  createdAt: Date;
  updatedAt: Date;

  static fromEntity(product: Product): ProductResponseDto {
    return {
      id: product.id,
      name: product.name,
      slug: product.slug,
      sku: product.sku,
      description: product.description,
      price: product.price,
      imagePath: product.imagePath,
      isActive: product.isActive,
      category: product.category
        ? { id: product.category.id, name: product.category.name }
        : null,
      stock: product.inventory?.quantity ?? 0,
      createdAt: product.createdAt,
      updatedAt: product.updatedAt,
    };
  }
}
