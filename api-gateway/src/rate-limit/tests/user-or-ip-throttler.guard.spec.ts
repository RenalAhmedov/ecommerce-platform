import { Reflector } from '@nestjs/core';
import type { ThrottlerStorage } from '@nestjs/throttler';
import type { Request } from 'express';
import { UserRole } from '../../users/enums/user-role.enum.js';
import { UserOrIpThrottlerGuard } from '../user-or-ip-throttler.guard.js';

class TestableGuard extends UserOrIpThrottlerGuard {
  trackerFor(req: Request) {
    return this.getTracker(req);
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
});
