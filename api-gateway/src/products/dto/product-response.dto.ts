import { Field, Int, ObjectType } from '@nestjs/graphql';
import type { Product } from '../entities/product.entity.js';
import { ProductCategoryDto } from './product-category.dto.js';

@ObjectType('Product')
export class ProductResponseDto {
  @Field(() => Int)
  id: number;

  @Field()
  name: string;

  @Field()
  slug: string;

  @Field()
  sku: string;

  @Field(() => String, { nullable: true })
  description: string | null;

  @Field({ description: 'Decimal string, e.g. "12.50"' })
  price: string;

  @Field(() => String, { nullable: true })
  imagePath: string | null;

  @Field()
  isActive: boolean;

  @Field(() => ProductCategoryDto, { nullable: true })
  category: ProductCategoryDto | null;

  @Field(() => Int)
  stock: number;

  @Field()
  createdAt: Date;

  @Field()
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
