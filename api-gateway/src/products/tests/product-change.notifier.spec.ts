import { ProductChangeNotifier } from '../product-change.notifier.js';
import type { ProductsCache } from '../products.cache.js';
import type { ProductSearchProducer } from '../queues/product-search.producer.js';

describe('ProductChangeNotifier', () => {
  it('drops cached responses and asks for a search index sync', async () => {
    const cache = { invalidate: vi.fn().mockResolvedValue(undefined) };
    const search = { requestSync: vi.fn().mockResolvedValue(undefined) };

    await new ProductChangeNotifier(
      cache as unknown as ProductsCache,
      search as unknown as ProductSearchProducer,
    ).productsChanged();

    expect(cache.invalidate).toHaveBeenCalledTimes(1);
    expect(search.requestSync).toHaveBeenCalledTimes(1);
  });
});
