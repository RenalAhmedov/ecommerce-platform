import { Injectable, Logger } from '@nestjs/common';
import { ThrottledWarning } from '../../common/logging/throttled-warning.js';
import { describeError } from '../../common/utils/describe-error.js';
import type { Product } from '../entities/product.entity.js';
import { ProductsRepository } from '../products.repository.js';
import type { ProductSearchFilters } from './product-search.query.js';
import { ProductSearchRepository } from './product-search.repository.js';

@Injectable()
export class ProductSearchService {
  private readonly unavailableWarning = new ThrottledWarning(
    new Logger(ProductSearchService.name),
  );

  constructor(
    private readonly productsRepository: ProductsRepository,
    private readonly productSearchRepository: ProductSearchRepository,
  ) {}

  async search(filters: ProductSearchFilters): Promise<[Product[], number]> {
    if (!this.productSearchRepository.isEnabled()) {
      return this.productsRepository.findActivePaginated(filters);
    }

    try {
      const { ids, total } = await this.productSearchRepository.search(filters);

      return [await this.productsRepository.findActiveByIds(ids), total];
    } catch (error) {
      this.unavailableWarning.warn(
        `Search falls back to Postgres, Elasticsearch unavailable (${describeError(error)})`,
      );
      return this.productsRepository.findActivePaginated(filters);
    }
  }
}
