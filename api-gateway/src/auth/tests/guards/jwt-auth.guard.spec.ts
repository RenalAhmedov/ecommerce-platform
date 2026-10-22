import { type ExecutionContext, UnauthorizedException } from '@nestjs/common';
import type { Reflector } from '@nestjs/core';
import { JwtService } from '@nestjs/jwt';
import { UserRole } from '../../../users/enums/user-role.enum.js';
import { JwtAuthGuard } from '../../guards/jwt-auth.guard.js';

describe('JwtAuthGuard', () => {
  const jwtService = new JwtService({
    secret: 'unit-test-secret-0123456789abcdefghij',
  });
  let reflector: { getAllAndOverride: ReturnType<typeof vi.fn> };
  let guard: JwtAuthGuard;
  let request: { headers: Record<string, string>; user?: unknown };

  const context = (type = 'http') =>
    ({
      getType: () => type,
      getHandler: () => undefined,
      getClass: () => undefined,
      switchToHttp: () => ({ getRequest: () => request }),
    }) as unknown as ExecutionContext;

  const payload = { sub: 7, email: 'ada@example.test', role: UserRole.Admin };

  beforeEach(() => {
    reflector = { getAllAndOverride: vi.fn().mockReturnValue(false) };
    guard = new JwtAuthGuard(jwtService, reflector as unknown as Reflector);
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

  it.each(['Bearer', 'bearer'])(
    'accepts a valid token with the "%s" scheme and attaches the user',
    async (scheme) => {
      request.headers.authorization = `${scheme} ${await jwtService.signAsync(payload)}`;

      await expect(guard.canActivate(context())).resolves.toBe(true);
      expect(request.user).toMatchObject(payload);
    },
  );
});
