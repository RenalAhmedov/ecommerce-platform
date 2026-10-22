import type { Redis } from 'ioredis';
import { RedisThrottlerStorage } from '../redis-throttler.storage.js';

describe('RedisThrottlerStorage', () => {
  let redis: { status: string; eval: ReturnType<typeof vi.fn> };
  let storage: RedisThrottlerStorage;

  const increment = () =>
    storage.increment('hash', 60_000, 5, 60_000, 'default');

  beforeEach(() => {
    redis = { status: 'ready', eval: vi.fn() };
    storage = new RedisThrottlerStorage(redis as unknown as Redis);
  });

  it('counts hits atomically in a prefixed key with the window as expiry', async () => {
    redis.eval.mockResolvedValue([1, 60_000]);

    await expect(increment()).resolves.toEqual({
      totalHits: 1,
      timeToExpire: 60,
      isBlocked: false,
      timeToBlockExpire: 60,
    });
    expect(redis.eval).toHaveBeenCalledWith(
      expect.stringContaining('INCR'),
      1,
      'rate-limit:default:hash',
      60_000,
    );
  });

  it('allows the last request within the limit', async () => {
    redis.eval.mockResolvedValue([5, 12_000]);

    await expect(increment()).resolves.toMatchObject({ isBlocked: false });
  });

  it('blocks once the limit is exceeded until the window resets', async () => {
    redis.eval.mockResolvedValue([6, 12_400]);

    await expect(increment()).resolves.toMatchObject({
      isBlocked: true,
      timeToBlockExpire: 13,
    });
  });

  it('fails open while Redis is not connected', async () => {
    redis.status = 'reconnecting';

    await expect(increment()).resolves.toMatchObject({ isBlocked: false });
    expect(redis.eval).not.toHaveBeenCalled();
  });

  it('fails open when Redis errors', async () => {
    redis.eval.mockRejectedValue(new Error('Command timed out'));

    await expect(increment()).resolves.toMatchObject({ isBlocked: false });
  });
});
