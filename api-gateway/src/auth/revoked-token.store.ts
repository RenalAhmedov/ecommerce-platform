import { Inject, Injectable, Logger } from '@nestjs/common';
import type { Redis } from 'ioredis';
import { REDIS_CLIENT } from '../cache/constants/cache.constants.js';
import { ThrottledWarning } from '../common/logging/throttled-warning.js';
import { describeError } from '../common/utils/describe-error.js';
import { REVOKED_TOKEN_KEY_PREFIX } from './constants/auth.constants.js';

@Injectable()
export class RevokedTokenStore {
  private readonly unavailableWarning = new ThrottledWarning(
    new Logger(RevokedTokenStore.name),
  );

  constructor(@Inject(REDIS_CLIENT) private readonly redis: Redis) {}

  async revoke(jti: string, expiresAt: number): Promise<void> {
    const secondsLeft = expiresAt - Math.floor(Date.now() / 1000);

    await this.redis.set(this.key(jti), '1', 'EX', Math.max(secondsLeft, 1));
  }

  async isRevoked(jti: string): Promise<boolean> {
    if (this.redis.status !== 'ready') {
      return false;
    }

    try {
      return (await this.redis.exists(this.key(jti))) === 1;
    } catch (error) {
      this.unavailableWarning.warn(
        `Revoked-token check skipped, Redis unavailable (${describeError(error)})`,
      );
      return false;
    }
  }

  private key(jti: string): string {
    return `${REVOKED_TOKEN_KEY_PREFIX}:${jti}`;
  }
}
