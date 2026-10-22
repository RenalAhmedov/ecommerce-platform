import { Injectable } from '@nestjs/common';
import { ThrottlerGuard } from '@nestjs/throttler';
import type { Request } from 'express';
import type { JwtPayload } from '../auth/interfaces/jwt-payload.interface.js';

@Injectable()
export class UserOrIpThrottlerGuard extends ThrottlerGuard {
  protected override getTracker(
    req: Request & { user?: JwtPayload },
  ): Promise<string> {
    return Promise.resolve(req.user ? `user:${req.user.sub}` : `ip:${req.ip}`);
  }
}
