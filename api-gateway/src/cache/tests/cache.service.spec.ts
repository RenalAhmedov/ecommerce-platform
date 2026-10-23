import type { ConfigService } from '@nestjs/config';
import type { Redis } from 'ioredis';
import type { EnvironmentVariables } from '../../config/interfaces/environment-variables.interface.js';
import { CacheService } from '../cache.service.js';

describe('CacheService', () => {
  let redis: {
    status: string;
    get: ReturnType<typeof vi.fn>;
    set: ReturnType<typeof vi.fn>;
    incr: ReturnType<typeof vi.fn>;
  };
  let cache: CacheService;
  const load = vi.fn();

  beforeEach(() => {
    redis = {
      status: 'ready',
      get: vi.fn().mockResolvedValue(null),
      set: vi.fn(),
      incr: vi.fn(),
    };
    load.mockReset().mockResolvedValue({ id: 1 });
    cache = new CacheService(
      redis as unknown as Redis,
      { get: () => 60 } as unknown as ConfigService<EnvironmentVariables, true>,
    );
  });

  it('returns a cached value without loading', async () => {
    redis.get.mockResolvedValue('{"id":2}');

    await expect(cache.wrap('key', load)).resolves.toEqual({ id: 2 });
    expect(load).not.toHaveBeenCalled();
  });

  it('loads and stores a missing value with the configured TTL', async () => {
    await expect(cache.wrap('key', load)).resolves.toEqual({ id: 1 });

    expect(redis.set).toHaveBeenCalledWith('key', '{"id":1}', 'EX', 60);
  });

  it('skips Redis entirely while it is not connected', async () => {
    redis.status = 'reconnecting';

    await expect(cache.wrap('key', load)).resolves.toEqual({ id: 1 });
    expect(redis.get).not.toHaveBeenCalled();
    expect(redis.set).not.toHaveBeenCalled();
    await expect(cache.increment('version')).resolves.toBe(false);
  });

  it('treats Redis errors as a cache miss instead of failing the request', async () => {
    redis.get.mockRejectedValue(new Error('Command timed out'));
    redis.set.mockRejectedValue(new Error('Command timed out'));

    await expect(cache.wrap('key', load)).resolves.toEqual({ id: 1 });
  });

  it('reports whether an increment reached Redis', async () => {
    await expect(cache.increment('version')).resolves.toBe(true);

    redis.incr.mockRejectedValue(new Error('Command timed out'));
    await expect(cache.increment('version')).resolves.toBe(false);
  });
});
