import type { ProductsRepository } from '../../products.repository.js';
import type { IndexableProduct } from '../../search/product-search-document.js';
import type { ProductSearchRepository } from '../../search/product-search.repository.js';
import { ProductSearchSyncService } from '../../search/product-search-sync.service.js';

describe('ProductSearchSyncService', () => {
  let products: {
    findIdsChangedSince: ReturnType<typeof vi.fn>;
    findIndexableByIds: ReturnType<typeof vi.fn>;
  };
  let index: {
    isEnabled: ReturnType<typeof vi.fn>;
    ensureIndex: ReturnType<typeof vi.fn>;
    syncedUntil: ReturnType<typeof vi.fn>;
    markSyncedUntil: ReturnType<typeof vi.fn>;
    apply: ReturnType<typeof vi.fn>;
  };
  let service: ProductSearchSyncService;

  const product = (id: number, overrides: Partial<IndexableProduct> = {}) =>
    ({
      id,
      name: `Product ${id}`,
      sku: `SKU-${id}`,
      description: null,
      categoryId: null,
      price: '10.00',
      isActive: true,
      createdAt: new Date('2026-10-24T08:00:00Z'),
      deletedAt: null,
      ...overrides,
    }) satisfies IndexableProduct;

  beforeEach(() => {
    vi.useFakeTimers({ now: new Date('2026-10-24T12:00:00Z') });
    products = {
      findIdsChangedSince: vi.fn().mockResolvedValue([]),
      findIndexableByIds: vi.fn().mockResolvedValue([]),
    };
    index = {
      isEnabled: vi.fn().mockReturnValue(true),
      ensureIndex: vi.fn().mockResolvedValue(false),
      syncedUntil: vi.fn().mockResolvedValue(new Date('2026-10-24T11:55:00Z')),
      markSyncedUntil: vi.fn(),
      apply: vi.fn(),
    };
    service = new ProductSearchSyncService(
      products as unknown as ProductsRepository,
      index as unknown as ProductSearchRepository,
    );
  });

  afterEach(() => {
    vi.useRealTimers();
  });

  it('does nothing when search is not configured', async () => {
    index.isEnabled.mockReturnValue(false);

    await expect(service.sync()).resolves.toEqual({ indexed: 0, removed: 0 });
    expect(index.ensureIndex).not.toHaveBeenCalled();
  });

  it('reads a minute before the last sync, so writes committed during it are not missed', async () => {
    await service.sync();

    expect(products.findIdsChangedSince).toHaveBeenCalledWith(
      new Date('2026-10-24T11:54:00Z'),
    );
    expect(index.markSyncedUntil).toHaveBeenCalledWith(
      new Date('2026-10-24T12:00:00Z'),
    );
  });

  it('indexes everything when the index was just created', async () => {
    index.ensureIndex.mockResolvedValue(true);

    await service.sync();

    expect(index.syncedUntil).not.toHaveBeenCalled();
    expect(products.findIdsChangedSince).toHaveBeenCalledWith(null);
  });

  it('indexes active products and removes inactive, deleted and vanished ones', async () => {
    products.findIdsChangedSince.mockResolvedValue([1, 2, 3, 4]);
    products.findIndexableByIds.mockResolvedValue([
      product(1),
      product(2, { isActive: false }),
      product(3, { deletedAt: new Date() }),
    ]);

    await expect(service.sync()).resolves.toEqual({ indexed: 1, removed: 3 });
    expect(index.apply).toHaveBeenCalledWith(
      [expect.objectContaining({ id: 1, price: 10 })],
      [2, 3, 4],
    );
  });

  it('works in batches of 500', async () => {
    products.findIdsChangedSince.mockResolvedValue(
      Array.from({ length: 1_001 }, (_, i) => i + 1),
    );

    await service.sync();

    expect(
      (products.findIndexableByIds.mock.calls as [number[]][]).map(
        ([ids]) => ids.length,
      ),
    ).toEqual([500, 500, 1]);
  });

  it('keeps the old sync time when writing fails, so the next run tries again', async () => {
    products.findIdsChangedSince.mockResolvedValue([1]);
    products.findIndexableByIds.mockResolvedValue([product(1)]);
    index.apply.mockRejectedValue(new Error('cluster_block_exception'));

    await expect(service.sync()).rejects.toThrow('cluster_block_exception');
    expect(index.markSyncedUntil).not.toHaveBeenCalled();
  });
});
