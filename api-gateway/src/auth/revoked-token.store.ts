import { Inject, Injectable, Logger } from '@nestjs/common';
import type { Redis } from 'ioredis';
import { REDIS_CLIENT } from '../cache/constants/cache.constants.js';
import { ThrottledWarning } from '../common/logging/throttled-warning.js';
import { describeError } from '../common/utils/describe-error.js';
import {
  REVOKED_TOKEN_KEY_PREFIX,
  USER_TOKENS_VALID_AFTER_KEY_PREFIX,
} from './constants/auth.constants.js';
import type { AccessTokenClaims } from './interfaces/jwt-payload.interface.js';

@Injectable()
export class RevokedTokenStore {
  private readonly unavailableWarning = new ThrottledWarning(
    new Logger(RevokedTokenStore.name),
  );

  constructor(@Inject(REDIS_CLIENT) private readonly redis: Redis) {}

  async revoke(jti: string, expiresAt: number): Promise<void> {
    const secondsLeft = expiresAt - Math.floor(Date.now() / 1000);

    await this.redis.set(
      `${REVOKED_TOKEN_KEY_PREFIX}:${jti}`,
      '1',
      'EX',
      Math.max(secondsLeft, 1),
    );
  }

  async isRevoked({ jti, sub, iat }: AccessTokenClaims): Promise<boolean> {
    if (this.redis.status !== 'ready') {
      return false;
    }

    try {
      const [revoked, validAfter] = await this.redis.mget(
        `${REVOKED_TOKEN_KEY_PREFIX}:${jti}`,
        `${USER_TOKENS_VALID_AFTER_KEY_PREFIX}:${sub}`,
      );

      return (
        // The cutoff is written by the admin service when a user is deleted, demoted or gets a new password.
        revoked !== null || (validAfter !== null && iat < Number(validAfter))
      );
    } catch (error) {
      this.unavailableWarning.warn(
        `Revoked-token check skipped, Redis unavailable (${describeError(error)})`,
      );
      return false;
    }
  }
}
