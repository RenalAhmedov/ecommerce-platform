import { Inject, Injectable, Logger } from '@nestjs/common';
import type { Redis } from 'ioredis';
import { REDIS_CLIENT } from '../cache/constants/cache.constants.js';
import { ThrottledWarning } from '../common/logging/throttled-warning.js';
import { describeError } from '../common/utils/describe-error.js';
import {
  WEBHOOK_EVENT_KEY_PREFIX,
  WEBHOOK_EVENT_TTL_SECONDS,
} from './constants/webhooks.constants.js';

/** Remembers handled webhook event ids, so a redelivered event is processed once. */
@Injectable()
export class WebhookEventStore {
  private readonly unavailableWarning = new ThrottledWarning(
    new Logger(WebhookEventStore.name),
  );

  constructor(@Inject(REDIS_CLIENT) private readonly redis: Redis) {}

  /**
   * Atomically records the event (SET NX) and returns false if it was already
   * recorded. If Redis is down the event is treated as new: webhook handlers
   * must therefore be safe to run twice.
   */
  async markIfNew(eventId: string): Promise<boolean> {
    try {
      const result = await this.redis.set(
        `${WEBHOOK_EVENT_KEY_PREFIX}:${eventId}`,
        '1',
        'EX',
        WEBHOOK_EVENT_TTL_SECONDS,
        'NX',
      );
      return result === 'OK';
    } catch (error) {
      this.unavailableWarning.warn(
        `Webhook de-duplication skipped, Redis unavailable (${describeError(error)})`,
      );
      return true;
    }
  }
}
