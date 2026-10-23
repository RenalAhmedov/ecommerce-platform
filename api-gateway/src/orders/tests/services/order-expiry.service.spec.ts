import { Test } from '@nestjs/testing';
import { ProductsCache } from '../../../products/products.cache.js';
import { OrdersRepository } from '../../orders.repository.js';
import { OrderExpiryService } from '../../services/order-expiry.service.js';
import { OrderReleaseService } from '../../services/order-release.service.js';

describe('OrderExpiryService', () => {
  let service: OrderExpiryService;
  let repository: { findStalePending: ReturnType<typeof vi.fn> };
  let release: { releasePending: ReturnType<typeof vi.fn> };
  let cache: { invalidate: ReturnType<typeof vi.fn> };

  const staleOrder = (id: number) => ({
    id,
    orderNumber: `ORD-${id}`,
    userId: 9,
    total: '10.00',
    items: [{ productId: 1, quantity: 2 }],
  });

  beforeEach(async () => {
    vi.useFakeTimers({ now: new Date('2026-01-01T12:00:00Z') });
    repository = { findStalePending: vi.fn().mockResolvedValue([]) };
    release = { releasePending: vi.fn().mockResolvedValue(true) };
    cache = { invalidate: vi.fn() };

    const moduleRef = await Test.createTestingModule({
      providers: [
        OrderExpiryService,
        { provide: OrdersRepository, useValue: repository },
        { provide: OrderReleaseService, useValue: release },
        { provide: ProductsCache, useValue: cache },
      ],
    }).compile();

    service = moduleRef.get(OrderExpiryService);
  });

  afterEach(() => {
    vi.useRealTimers();
  });

  it('looks for pending orders older than 15 minutes, 100 at a time', async () => {
    await service.expireStaleOrders();

    expect(repository.findStalePending).toHaveBeenCalledWith(
      new Date('2026-01-01T11:45:00Z'),
      100,
    );
  });

  it('releases each stale order and invalidates the cache once', async () => {
    repository.findStalePending.mockResolvedValue([
      staleOrder(1),
      staleOrder(2),
    ]);

    await expect(service.expireStaleOrders()).resolves.toBe(2);

    expect(release.releasePending).toHaveBeenCalledWith({
      id: 1,
      orderNumber: 'ORD-1',
      userId: 9,
      total: '10.00',
      items: [{ productId: 1, quantity: 2 }],
    });
    expect(cache.invalidate).toHaveBeenCalledTimes(1);
  });

  it('only counts orders it actually released', async () => {
    repository.findStalePending.mockResolvedValue([
      staleOrder(1),
      staleOrder(2),
    ]);
    release.releasePending
      .mockResolvedValueOnce(false)
      .mockResolvedValueOnce(true);

    await expect(service.expireStaleOrders()).resolves.toBe(1);
  });

  it('leaves the cache alone when nothing was released', async () => {
    repository.findStalePending.mockResolvedValue([staleOrder(1)]);
    release.releasePending.mockResolvedValue(false);

    await expect(service.expireStaleOrders()).resolves.toBe(0);
    expect(cache.invalidate).not.toHaveBeenCalled();
  });
});
