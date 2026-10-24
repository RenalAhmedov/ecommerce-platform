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
});
