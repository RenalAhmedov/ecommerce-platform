import { toMoney } from '../../common/utils/money.js';
import type { CreateProductDto } from '../dto/create-product.dto.js';
import type { UpdateProductDto } from '../dto/update-product.dto.js';
import type { NewProduct, ProductChanges } from '../types/product.types.js';

export function toNewProduct(dto: CreateProductDto, slug: string): NewProduct {
  return {
    name: dto.name,
    slug,
    sku: dto.sku,
    description: dto.description ?? null,
    price: toMoney(dto.price),
    categoryId: dto.categoryId ?? null,
    isActive: dto.isActive ?? true,
  };
}

export function toProductChanges(dto: UpdateProductDto): ProductChanges {
  const { price, ...fields } = dto;
  const changes: ProductChanges = { ...fields };

  if (price !== undefined) {
    changes.price = toMoney(price);
  }

  return changes;
}
