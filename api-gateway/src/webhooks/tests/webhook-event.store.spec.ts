import type { Redis } from 'ioredis';
import { WebhookEventStore } from '../webhook-event.store.js';

describe('WebhookEventStore', () => {
  let redis: { set: ReturnType<typeof vi.fn> };
  let store: WebhookEventStore;

  beforeEach(() => {
    redis = { set: vi.fn() };
    store = new WebhookEventStore(redis as unknown as Redis);
  });

  it('records a new event atomically for a day', async () => {
    redis.set.mockResolvedValue('OK');

    await expect(store.markIfNew('event-1')).resolves.toBe(true);
    expect(redis.set).toHaveBeenCalledWith(
      'webhook:event:event-1',
      '1',
      'EX',
      86_400,
      'NX',
    );
  });

  it('reports an event it has already recorded', async () => {
    redis.set.mockResolvedValue(null);

    await expect(store.markIfNew('event-1')).resolves.toBe(false);
  });

  it('treats the event as new when Redis is down, so it is still handled', async () => {
    redis.set.mockRejectedValue(new Error('Connection is closed.'));

    await expect(store.markIfNew('event-1')).resolves.toBe(true);
  });
});
