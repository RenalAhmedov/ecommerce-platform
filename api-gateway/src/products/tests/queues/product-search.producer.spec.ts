import type { Queue } from 'bullmq';
import { ProductSearchProducer } from '../../queues/product-search.producer.js';
import type { ProductSearchRepository } from '../../search/product-search.repository.js';

describe('ProductSearchProducer', () => {
  let queue: { add: ReturnType<typeof vi.fn>; on: ReturnType<typeof vi.fn> };
  let index: { isEnabled: ReturnType<typeof vi.fn> };
  let producer: ProductSearchProducer;

  beforeEach(() => {
    queue = { add: vi.fn(), on: vi.fn() };
    index = { isEnabled: vi.fn().mockReturnValue(true) };
    producer = new ProductSearchProducer(
      queue as unknown as Queue,
      index as unknown as ProductSearchRepository,
    );
  });

  it('queues one sync at a time, keeping a follow-up for changes made during it', async () => {
    await producer.requestSync();

    expect(queue.add).toHaveBeenCalledWith(
      'sync-products',
      {},
      expect.objectContaining({
        deduplication: { id: 'sync-products', keepLastIfActive: true },
        attempts: 5,
      }),
    );
  });

  it('never fails the product write that asked for it', async () => {
    queue.add.mockRejectedValue(new Error('Connection is closed.'));

    await expect(producer.requestSync()).resolves.toBeUndefined();
  });

  it('does nothing when search is not configured', async () => {
    index.isEnabled.mockReturnValue(false);

    await producer.requestSync();

    expect(queue.add).not.toHaveBeenCalled();
  });

  it('schedules the periodic sync and builds the index at start', async () => {
    const scheduled = Object.assign(queue, {
      waitUntilReady: vi.fn().mockResolvedValue(undefined),
      upsertJobScheduler: vi.fn().mockResolvedValue(undefined),
    });

    producer.onApplicationBootstrap();
    await vi.waitFor(() => expect(queue.add).toHaveBeenCalled());

    expect(scheduled.upsertJobScheduler).toHaveBeenCalledWith(
      'sync-products',
      { every: 300_000 },
      expect.objectContaining({ name: 'sync-products' }),
    );
  });

  it('schedules nothing when search is not configured', () => {
    const scheduled = Object.assign(queue, { waitUntilReady: vi.fn() });
    index.isEnabled.mockReturnValue(false);

    producer.onApplicationBootstrap();

    expect(scheduled.waitUntilReady).not.toHaveBeenCalled();
  });
});
