import { Inject, Injectable } from '@nestjs/common';
import type { Redis } from 'ioredis';
import { REDIS_CLIENT } from '../cache/constants/cache.constants.js';
import {
  PROCESSING_TTL_SECONDS,
  RESULT_TTL_SECONDS,
} from './constants/idempotency.constants.js';
import type {
  CompletedIdempotencyRecord,
  IdempotencyRecord,
} from './types/idempotency-record.type.js';

@Injectable()
export class IdempotencyStore {
  constructor(@Inject(REDIS_CLIENT) private readonly redis: Redis) {}

  async claim(
    key: string,
    fingerprint: string,
  ): Promise<IdempotencyRecord | null> {
    const processing: IdempotencyRecord = { state: 'processing', fingerprint };
    const claimed = await this.redis.set(
      key,
      JSON.stringify(processing),
      'EX',
      PROCESSING_TTL_SECONDS,
      'NX',
    );

    if (claimed === 'OK') {
      return null;
    }

    const existing = await this.redis.get(key);

    return existing === null
      ? this.claim(key, fingerprint)
      : (JSON.parse(existing) as IdempotencyRecord);
  }

  async complete(
    key: string,
    record: CompletedIdempotencyRecord,
  ): Promise<void> {
    await this.redis.set(key, JSON.stringify(record), 'EX', RESULT_TTL_SECONDS);
  }

  async release(key: string): Promise<void> {
    await this.redis.del(key);
  }
}
