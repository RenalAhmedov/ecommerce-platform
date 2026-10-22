import { type INestApplication, Logger } from '@nestjs/common';
import type { Redis } from 'ioredis';
import { setTimeout as sleep } from 'node:timers/promises';
import { describeError } from '../common/utils/describe-error.js';

const RETRY_DELAY_MS = 2_000;
const logger = new Logger('EventListener');

export async function startEventListener(
  app: INestApplication,
  redis: Redis,
): Promise<void> {
  for (;;) {
    if (redis.status !== 'ready') {
      logger.warn('Waiting for Redis before subscribing to events');
      await new Promise((resolve) => redis.once('ready', resolve));
    }

    try {
      await app.startAllMicroservices();
      logger.log('Subscribed to events');
      return;
    } catch (error) {
      logger.error(
        `Could not subscribe to events (${describeError(error)}), retrying`,
      );
      await sleep(RETRY_DELAY_MS);
    }
  }
}
