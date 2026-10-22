import { Test } from '@nestjs/testing';
import { CacheService } from '../../cache/cache.service.js';
import { ProductQueryDto } from '../dto/product-query.dto.js';
import type { ProductResponseDto } from '../dto/product-response.dto.js';
import { ProductsCache } from '../products.cache.js';

describe('ProductsCache', () => {
  let productsCache: ProductsCache;
  let cache: {
    get: ReturnType<typeof vi.fn>;
    wrap: ReturnType<typeof vi.fn>;
    increment: ReturnType<typeof vi.fn>;
  };

  const load = () =>
    Promise.resolve({
      data: [],
      meta: { page: 1, limit: 20, total: 0, totalPages: 0 },
    });
  const filters = (values: Partial<ProductQueryDto> = {}) =>
    Object.assign(new ProductQueryDto(), values);
  const wrappedKey = (call = 0) => cache.wrap.mock.calls[call][0] as string;

  beforeEach(async () => {
    cache = {
      get: vi.fn().mockResolvedValue(4),
      wrap: vi.fn((_key: string, loader: () => Promise<unknown>) => loader()),
      increment: vi.fn().mockResolvedValue(true),
    };

    const moduleRef = await Test.createTestingModule({
      providers: [ProductsCache, { provide: CacheService, useValue: cache }],
    }).compile();

    productsCache = moduleRef.get(ProductsCache);
  });

  it('builds detail keys from the current version', async () => {
    await productsCache.detail(5, () =>
      Promise.resolve({} as ProductResponseDto),
    );

    expect(wrappedKey()).toBe('products:v4:detail:5');
  });

  it('uses version 0 when no version has been stored yet', async () => {
    cache.get.mockResolvedValue(null);

    await productsCache.list(filters(), load);

    expect(wrappedKey()).toMatch(/^products:v0:list:[0-9a-f]{40}$/);
  });

  it('uses the same list key for the same filters and a different one otherwise', async () => {
    await productsCache.list(filters({ search: 'Keyboard' }), load);
    await productsCache.list(filters({ search: 'keyboard' }), load);
    await productsCache.list(filters({ search: 'keyboard', page: 2 }), load);

    expect(wrappedKey(0)).toBe(wrappedKey(1));
    expect(wrappedKey(2)).not.toBe(wrappedKey(0));
  });

  it('invalidates by incrementing the version', async () => {
    await productsCache.invalidate();

    expect(cache.increment).toHaveBeenCalledWith('products:cache-version');
  });

  it('retries a failed invalidation before the next read', async () => {
    cache.increment.mockResolvedValueOnce(false);
    await productsCache.invalidate();

    await productsCache.list(filters(), load);

    expect(cache.increment).toHaveBeenCalledTimes(2);
    expect(cache.increment.mock.invocationCallOrder[1]).toBeLessThan(
      cache.wrap.mock.invocationCallOrder[0],
    );
  });

  it('does not retry once an invalidation succeeded', async () => {
    await productsCache.invalidate();

    await productsCache.list(filters(), load);

    expect(cache.increment).toHaveBeenCalledTimes(1);
  });
});
