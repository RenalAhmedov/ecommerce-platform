import type { Redis } from 'ioredis';
import { RevokedTokenStore } from '../revoked-token.store.js';

describe('RevokedTokenStore', () => {
  let redis: {
    status: string;
    set: ReturnType<typeof vi.fn>;
    exists: ReturnType<typeof vi.fn>;
  };
  let store: RevokedTokenStore;

  beforeEach(() => {
    vi.useFakeTimers({ now: new Date('2026-01-01T12:00:00Z') });
    redis = { status: 'ready', set: vi.fn(), exists: vi.fn() };
    store = new RevokedTokenStore(redis as unknown as Redis);
  });

  afterEach(() => {
    vi.useRealTimers();
  });

  const nowInSeconds = () => Math.floor(Date.now() / 1000);

  it('keeps a revoked token only until it would expire anyway', async () => {
    await store.revoke('token-1', nowInSeconds() + 1_800);

    expect(redis.set).toHaveBeenCalledWith(
      'auth:revoked-token:token-1',
      '1',
      'EX',
      1_800,
    );
  });

  it('keeps a token that is about to expire for at least one second', async () => {
    await store.revoke('token-1', nowInSeconds());

    expect(redis.set).toHaveBeenCalledWith(
      'auth:revoked-token:token-1',
      '1',
      'EX',
      1,
    );
  });

  it('lets revoke failures escape so logout can report them', async () => {
    redis.set.mockRejectedValue(new Error("Stream isn't writeable"));

    await expect(store.revoke('token-1', nowInSeconds() + 60)).rejects.toThrow(
      "Stream isn't writeable",
    );
  });

  it('reports a revoked token', async () => {
    redis.exists.mockResolvedValue(1);

    await expect(store.isRevoked('token-1')).resolves.toBe(true);
    expect(redis.exists).toHaveBeenCalledWith('auth:revoked-token:token-1');
  });

  it('reports a token that was never revoked', async () => {
    redis.exists.mockResolvedValue(0);

    await expect(store.isRevoked('token-1')).resolves.toBe(false);
  });

  it('fails open while Redis is unavailable', async () => {
    redis.status = 'reconnecting';
    await expect(store.isRevoked('token-1')).resolves.toBe(false);

    redis.status = 'ready';
    redis.exists.mockRejectedValue(new Error('Command timed out'));
    await expect(store.isRevoked('token-1')).resolves.toBe(false);
  });
});
