import {
  BadRequestException,
  ConflictException,
  Injectable,
} from '@nestjs/common';
import { CategoriesService } from '../../categories/categories.service.js';
import { slugify } from '../../common/utils/slugify.js';
import type { CreateProductDto } from '../dto/create-product.dto.js';
import type { ProductQueryDto } from '../dto/product-query.dto.js';
import type { ProductsRepository } from '../products.repository.js';
import type { ProductChanges } from '../types/product.types.js';

@Injectable()
export class ProductValidator {
  constructor(private readonly categoriesService: CategoriesService) {}

  ensureValidPriceRange({ minPrice, maxPrice }: ProductQueryDto): void {
    if (
      minPrice !== undefined &&
      maxPrice !== undefined &&
      minPrice > maxPrice
    ) {
      throw new BadRequestException(
        'minPrice must not be greater than maxPrice',
      );
    }
  }

  resolveSlug(dto: CreateProductDto): string {
    const slug = dto.slug ?? slugify(dto.name);

    if (!slug) {
      throw new BadRequestException(
        'Could not generate a slug from the name, please provide one',
      );
    }

    return slug;
  }

  ensureHasChanges(changes: ProductChanges): void {
    if (Object.keys(changes).length === 0) {
      throw new BadRequestException('Provide at least one field to update');
    }
  }

  async ensureCategoryExists(categoryId?: number | null): Promise<void> {
    if (categoryId === undefined || categoryId === null) {
      return;
    }

    if (!(await this.categoriesService.existsById(categoryId))) {
      throw new BadRequestException('Category not found');
    }
  }

  async ensureSkuAvailable(
    products: ProductsRepository,
    sku: string,
    excludeId?: number,
  ): Promise<void> {
    if (await products.existsBySku(sku, excludeId)) {
      throw new ConflictException('A product with this SKU already exists');
    }
  }

  async ensureSlugAvailable(
    products: ProductsRepository,
    slug: string,
    excludeId?: number,
  ): Promise<void> {
    if (await products.existsBySlug(slug, excludeId)) {
      throw new ConflictException('A product with this slug already exists');
    }
  }
}
