import { type ExecutionContext, UnauthorizedException } from '@nestjs/common';
import type { Reflector } from '@nestjs/core';
import { JwtService } from '@nestjs/jwt';
import { UserRole } from '../../../users/enums/user-role.enum.js';
import { JwtAuthGuard } from '../../guards/jwt-auth.guard.js';
import type { RevokedTokenStore } from '../../revoked-token.store.js';

describe('JwtAuthGuard', () => {
  const jwtService = new JwtService({
    secret: 'unit-test-secret-0123456789abcdefghij',
  });
  let reflector: { getAllAndOverride: ReturnType<typeof vi.fn> };
  let revokedTokenStore: { isRevoked: ReturnType<typeof vi.fn> };
  let guard: JwtAuthGuard;
  let request: { headers: Record<string, string>; user?: unknown };

  const context = (type = 'http') =>
    ({
      getType: () => type,
      getHandler: () => undefined,
      getClass: () => undefined,
      switchToHttp: () => ({ getRequest: () => request }),
    }) as unknown as ExecutionContext;

  const payload = {
    sub: 7,
    email: 'ada@example.test',
    role: UserRole.Admin,
    jti: 'token-1',
  };
  const authorize = async (claims: object = payload, scheme = 'Bearer') => {
    request.headers.authorization = `${scheme} ${await jwtService.signAsync(claims)}`;
  };

  beforeEach(() => {
    reflector = { getAllAndOverride: vi.fn().mockReturnValue(false) };
    revokedTokenStore = { isRevoked: vi.fn().mockResolvedValue(false) };
    guard = new JwtAuthGuard(
      jwtService,
      reflector as unknown as Reflector,
      revokedTokenStore as unknown as RevokedTokenStore,
    );
    request = { headers: {} };
  });

  it('lets public routes through without a token', async () => {
    reflector.getAllAndOverride.mockReturnValue(true);

    await expect(guard.canActivate(context())).resolves.toBe(true);
  });

  it('ignores non-HTTP contexts such as Redis events', async () => {
    await expect(guard.canActivate(context('rpc'))).resolves.toBe(true);
  });

  it('rejects a request without a token', async () => {
    await expect(guard.canActivate(context())).rejects.toThrow(
      new UnauthorizedException('Missing access token'),
    );
  });

  it('rejects a non-bearer scheme', async () => {
    request.headers.authorization = 'Basic dXNlcjpwYXNz';

    await expect(guard.canActivate(context())).rejects.toThrow(
      UnauthorizedException,
    );
  });

  it('rejects a token signed with another secret', async () => {
    const forged = await new JwtService({
      secret: 'some-other-secret-0123456789abcdefghij',
    }).signAsync(payload);
    request.headers.authorization = `Bearer ${forged}`;

    await expect(guard.canActivate(context())).rejects.toThrow(
      new UnauthorizedException('Invalid or expired access token'),
    );
  });

  it('rejects a token without a token id, which could never be revoked', async () => {
    await authorize({ sub: 7, email: 'ada@example.test', role: 'admin' });

    await expect(guard.canActivate(context())).rejects.toThrow(
      new UnauthorizedException('Invalid or expired access token'),
    );
  });

  it('rejects a token without an issue time, which no cutoff could catch', async () => {
    request.headers.authorization = `Bearer ${await jwtService.signAsync(payload, { noTimestamp: true })}`;

    await expect(guard.canActivate(context())).rejects.toThrow(
      new UnauthorizedException('Invalid or expired access token'),
    );
  });

  it('rejects a revoked token', async () => {
    await authorize();
    revokedTokenStore.isRevoked.mockResolvedValue(true);

    await expect(guard.canActivate(context())).rejects.toThrow(
      new UnauthorizedException('Access token has been revoked'),
    );
    expect(revokedTokenStore.isRevoked).toHaveBeenCalledWith(
      expect.objectContaining({ jti: 'token-1', sub: 7 }),
    );
    expect(request.user).toBeUndefined();
  });

  it.each(['Bearer', 'bearer'])(
    'accepts a valid token with the "%s" scheme and attaches the claims',
    async (scheme) => {
      await authorize(payload, scheme);

      await expect(guard.canActivate(context())).resolves.toBe(true);
      expect(request.user).toMatchObject({
        ...payload,
        iat: expect.any(Number),
      });
    },
  );
});
