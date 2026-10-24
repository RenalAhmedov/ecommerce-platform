import { Injectable } from '@nestjs/common';
import { ProductsCache } from './products.cache.js';
import { ProductSearchProducer } from './queues/product-search.producer.js';

/**
 * Called after products themselves changed (here or in the admin service):
 * cached responses are dropped and the search index is brought up to date.
 * Stock-only changes (orders) need just the cache: stock isn't searched.
 */
@Injectable()
export class ProductChangeNotifier {
  constructor(
    private readonly productsCache: ProductsCache,
    private readonly productSearchProducer: ProductSearchProducer,
  ) {}

  async productsChanged(): Promise<void> {
    await this.productsCache.invalidate();
    await this.productSearchProducer.requestSync();
  }
}
