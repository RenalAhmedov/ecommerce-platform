import type { Redis } from 'ioredis';
import { UserRole } from '../../users/enums/user-role.enum.js';
import type { AccessTokenClaims } from '../interfaces/jwt-payload.interface.js';
import { RevokedTokenStore } from '../revoked-token.store.js';

describe('RevokedTokenStore', () => {
  let redis: {
    status: string;
    set: ReturnType<typeof vi.fn>;
    mget: ReturnType<typeof vi.fn>;
  };
  let store: RevokedTokenStore;

  beforeEach(() => {
    vi.useFakeTimers({ now: new Date('2026-01-01T12:00:00Z') });
    redis = { status: 'ready', set: vi.fn(), mget: vi.fn() };
    store = new RevokedTokenStore(redis as unknown as Redis);
  });

  afterEach(() => {
    vi.useRealTimers();
  });

  const nowInSeconds = () => Math.floor(Date.now() / 1000);
  const claims: AccessTokenClaims = {
    sub: 7,
    email: 'ada@example.test',
    role: UserRole.Customer,
    jti: 'token-1',
    iat: 1_000,
    exp: 4_600,
  };

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

  it('reports a logged-out token, reading both keys in one round trip', async () => {
    redis.mget.mockResolvedValue(['1', null]);

    await expect(store.isRevoked(claims)).resolves.toBe(true);
    expect(redis.mget).toHaveBeenCalledWith(
      'auth:revoked-token:token-1',
      'auth:user-tokens-valid-after:7',
    );
  });

  it('reports a token that was never revoked', async () => {
    redis.mget.mockResolvedValue([null, null]);

    await expect(store.isRevoked(claims)).resolves.toBe(false);
  });

  it.each([
    ['issued before the cutoff', '1001', true],
    ['issued in the second of the cutoff', '1000', false],
    ['issued after the cutoff', '999', false],
  ])(
    'checks a token %s that the admin service set for its user',
    async (_case, validAfter, revoked) => {
      redis.mget.mockResolvedValue([null, validAfter]);

      await expect(store.isRevoked(claims)).resolves.toBe(revoked);
    },
  );

  it('fails open while Redis is unavailable', async () => {
    redis.status = 'reconnecting';
    await expect(store.isRevoked(claims)).resolves.toBe(false);

    redis.status = 'ready';
    redis.mget.mockRejectedValue(new Error('Command timed out'));
    await expect(store.isRevoked(claims)).resolves.toBe(false);
  });
});
