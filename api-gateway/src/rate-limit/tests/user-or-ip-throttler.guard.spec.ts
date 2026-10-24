import type { ExecutionContext } from '@nestjs/common';
import { Reflector } from '@nestjs/core';
import type { ThrottlerStorage } from '@nestjs/throttler';
import type { Request } from 'express';
import { UserRole } from '../../users/enums/user-role.enum.js';
import { UserOrIpThrottlerGuard } from '../user-or-ip-throttler.guard.js';

class TestableGuard extends UserOrIpThrottlerGuard {
  trackerFor(req: Request) {
    return this.getTracker(req);
  }

  requestResponseOf(context: ExecutionContext) {
    return this.getRequestResponse(context);
  }
}

describe('UserOrIpThrottlerGuard', () => {
  const guard = new TestableGuard(
    { throttlers: [] },
    {} as ThrottlerStorage,
    new Reflector(),
  );

  it('tracks logged-in users by id, so users behind one IP do not share a limit', async () => {
    const req = {
      ip: '10.0.0.1',
      user: { sub: 7, email: 'a@b.c', role: UserRole.Customer },
    } as unknown as Request;

    await expect(guard.trackerFor(req)).resolves.toBe('user:7');
  });

  it('tracks anonymous requests by IP', async () => {
    const req = { ip: '10.0.0.1' } as Request;

    await expect(guard.trackerFor(req)).resolves.toBe('ip:10.0.0.1');
  });

  it('finds the request behind REST calls and GraphQL operations alike', () => {
    const req = { ip: '10.0.0.1' };
    const res = {};
    const context = (type: string) =>
      ({
        getType: () => type,
        getArgs: () => [{}, {}, { req, res }, {}],
        getClass: () => undefined,
        getHandler: () => undefined,
        switchToHttp: () => ({ getRequest: () => req, getResponse: () => res }),
      }) as unknown as ExecutionContext;

    expect(guard.requestResponseOf(context('http'))).toEqual({ req, res });
    expect(guard.requestResponseOf(context('graphql'))).toEqual({ req, res });
    expect(() => guard.requestResponseOf(context('rpc'))).toThrow(
      'Rate limiting runs only for HTTP and GraphQL requests',
    );
  });
});
