import { Injectable } from '@nestjs/common';
import { ProductsCache } from './products.cache.js';
import { ProductSearchProducer } from './queues/product-search.producer.js';

@Injectable()
export class ProductChangeNotifier {
  constructor(
    private readonly productsCache: ProductsCache,
    private readonly productSearchProducer: ProductSearchProducer,
  ) {}

  // Stock-only changes (orders) only invalidate the cache: stock isn't searched.
  async productsChanged(): Promise<void> {
    await this.productsCache.invalidate();
    await this.productSearchProducer.requestSync();
  }
}
