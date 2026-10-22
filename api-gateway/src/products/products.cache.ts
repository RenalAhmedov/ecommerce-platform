import { Injectable } from '@nestjs/common';
import { createHash } from 'node:crypto';
import { CacheService } from '../cache/cache.service.js';
import type { Paginated } from '../common/interfaces/paginated.interface.js';
import type { ProductQueryDto } from './dto/product-query.dto.js';
import type { ProductResponseDto } from './dto/product-response.dto.js';

const VERSION_KEY = 'products:cache-version';

@Injectable()
export class ProductsCache {
  private invalidationPending = false;

  constructor(private readonly cache: CacheService) {}

  async list(
    filters: ProductQueryDto,
    load: () => Promise<Paginated<ProductResponseDto>>,
  ): Promise<Paginated<ProductResponseDto>> {
    const key = `${await this.keyPrefix()}:list:${hashFilters(filters)}`;

    return this.cache.wrap(key, load);
  }

  async detail(
    id: number,
    load: () => Promise<ProductResponseDto>,
  ): Promise<ProductResponseDto> {
    const key = `${await this.keyPrefix()}:detail:${id}`;

    return this.cache.wrap(key, load);
  }

  async invalidate(): Promise<void> {
    this.invalidationPending = !(await this.cache.increment(VERSION_KEY));
  }

  private async keyPrefix(): Promise<string> {
    if (this.invalidationPending) {
      await this.invalidate();
    }

    const version = (await this.cache.get<number>(VERSION_KEY)) ?? 0;

    return `products:v${version}`;
  }
}

function hashFilters(filters: ProductQueryDto): string {
  const normalized = [
    filters.page,
    filters.limit,
    filters.search?.toLowerCase() ?? null,
    filters.categoryId ?? null,
    filters.minPrice ?? null,
    filters.maxPrice ?? null,
    filters.sortBy,
    filters.sortOrder,
  ];

  return createHash('sha1').update(JSON.stringify(normalized)).digest('hex');
}
