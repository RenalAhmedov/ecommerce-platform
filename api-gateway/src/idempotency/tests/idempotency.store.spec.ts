import type { Redis } from 'ioredis';
import { IdempotencyStore } from '../idempotency.store.js';

describe('IdempotencyStore', () => {
  let redis: {
    set: ReturnType<typeof vi.fn>;
    get: ReturnType<typeof vi.fn>;
    del: ReturnType<typeof vi.fn>;
  };
  let store: IdempotencyStore;

  beforeEach(() => {
    redis = { set: vi.fn(), get: vi.fn(), del: vi.fn() };
    store = new IdempotencyStore(redis as unknown as Redis);
  });

  it('claims a new key atomically for five minutes', async () => {
    redis.set.mockResolvedValue('OK');

    await expect(store.claim('key', 'abc')).resolves.toBeNull();
    expect(redis.set).toHaveBeenCalledWith(
      'key',
      '{"state":"processing","fingerprint":"abc"}',
      'EX',
      300,
      'NX',
    );
  });

  it('returns the record of a key that is already taken', async () => {
    const record = { state: 'succeeded', fingerprint: 'abc', body: { id: 1 } };
    redis.set.mockResolvedValue(null);
    redis.get.mockResolvedValue(JSON.stringify(record));

    await expect(store.claim('key', 'abc')).resolves.toEqual(record);
  });

  it('claims again when the existing key expired in between', async () => {
    redis.set.mockResolvedValueOnce(null).mockResolvedValueOnce('OK');
    redis.get.mockResolvedValue(null);

    await expect(store.claim('key', 'abc')).resolves.toBeNull();
    expect(redis.set).toHaveBeenCalledTimes(2);
  });

  it('keeps a completed result for a day and can release a claim', async () => {
    await store.complete('key', {
      state: 'succeeded',
      fingerprint: 'abc',
      body: null,
    });
    await store.release('key');

    expect(redis.set).toHaveBeenCalledWith(
      'key',
      expect.any(String),
      'EX',
      86_400,
    );
    expect(redis.del).toHaveBeenCalledWith('key');
  });
});
