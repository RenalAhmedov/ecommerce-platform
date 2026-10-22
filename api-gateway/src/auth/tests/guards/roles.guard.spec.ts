import { type ExecutionContext, ForbiddenException } from '@nestjs/common';
import type { Reflector } from '@nestjs/core';
import { UserRole } from '../../../users/enums/user-role.enum.js';
import { RolesGuard } from '../../guards/roles.guard.js';

describe('RolesGuard', () => {
  let reflector: { getAllAndOverride: ReturnType<typeof vi.fn> };
  let guard: RolesGuard;

  const context = (user?: { role: UserRole }, type = 'http') =>
    ({
      getType: () => type,
      getHandler: () => undefined,
      getClass: () => undefined,
      switchToHttp: () => ({ getRequest: () => ({ user }) }),
    }) as unknown as ExecutionContext;

  beforeEach(() => {
    reflector = { getAllAndOverride: vi.fn() };
    guard = new RolesGuard(reflector as unknown as Reflector);
  });

  it('allows routes without @Roles', () => {
    reflector.getAllAndOverride.mockReturnValue(undefined);

    expect(guard.canActivate(context({ role: UserRole.Customer }))).toBe(true);
  });

  it('allows a user with a required role', () => {
    reflector.getAllAndOverride.mockReturnValue([UserRole.Admin]);

    expect(guard.canActivate(context({ role: UserRole.Admin }))).toBe(true);
  });

  it('forbids a user without a required role', () => {
    reflector.getAllAndOverride.mockReturnValue([UserRole.Admin]);

    expect(() =>
      guard.canActivate(context({ role: UserRole.Customer })),
    ).toThrow(ForbiddenException);
  });

  it('forbids when there is no authenticated user', () => {
    reflector.getAllAndOverride.mockReturnValue([UserRole.Admin]);

    expect(() => guard.canActivate(context())).toThrow(ForbiddenException);
  });

  it('ignores non-HTTP contexts such as Redis events', () => {
    expect(guard.canActivate(context(undefined, 'rpc'))).toBe(true);
    expect(reflector.getAllAndOverride).not.toHaveBeenCalled();
  });
});
