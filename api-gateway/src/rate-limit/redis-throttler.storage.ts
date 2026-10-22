import { Logger } from '@nestjs/common';
import type { ThrottlerStorage } from '@nestjs/throttler';
import type { Redis } from 'ioredis';
import { ThrottledWarning } from '../common/logging/throttled-warning.js';
import { describeError } from '../common/utils/describe-error.js';
import { RATE_LIMIT_KEY_PREFIX } from './constants/rate-limit.constants.js';

type ThrottlerStorageRecord = Awaited<
  ReturnType<ThrottlerStorage['increment']>
>;

const INCREMENT_SCRIPT = `
local hits = redis.call('INCR', KEYS[1])
if hits == 1 then
  redis.call('PEXPIRE', KEYS[1], ARGV[1])
end
return { hits, redis.call('PTTL', KEYS[1]) }
`;

const UNLIMITED: ThrottlerStorageRecord = {
  totalHits: 0,
  timeToExpire: 0,
  isBlocked: false,
  timeToBlockExpire: 0,
};

export class RedisThrottlerStorage implements ThrottlerStorage {
  private readonly unavailableWarning = new ThrottledWarning(
    new Logger(RedisThrottlerStorage.name),
  );

  constructor(private readonly redis: Redis) {}

  async increment(
    key: string,
    ttl: number,
    limit: number,
    _blockDuration: number,
    throttlerName: string,
  ): Promise<ThrottlerStorageRecord> {
    if (this.redis.status !== 'ready') {
      return UNLIMITED;
    }

    try {
      const [totalHits, remainingMs] = (await this.redis.eval(
        INCREMENT_SCRIPT,
        1,
        `${RATE_LIMIT_KEY_PREFIX}:${throttlerName}:${key}`,
        ttl,
      )) as [number, number];
      const timeToExpire = Math.ceil(remainingMs / 1000);

      return {
        totalHits,
        timeToExpire,
        isBlocked: totalHits > limit,
        timeToBlockExpire: timeToExpire,
      };
    } catch (error) {
      this.unavailableWarning.warn(
        `Rate limiting skipped, Redis unavailable (${describeError(error)})`,
      );
      return UNLIMITED;
    }
  }
}
