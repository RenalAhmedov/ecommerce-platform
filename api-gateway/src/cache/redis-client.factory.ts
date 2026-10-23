import { Logger } from '@nestjs/common';
import type { ConfigService } from '@nestjs/config';
import { Redis } from 'ioredis';
import { describeError } from '../common/utils/describe-error.js';
import type { EnvironmentVariables } from '../config/interfaces/environment-variables.interface.js';

export function createRedisClient(
  config: ConfigService<EnvironmentVariables, true>,
): Redis {
  const logger = new Logger('Redis');
  const redis = new Redis({
    host: config.get('REDIS_HOST'),
    port: config.get('REDIS_PORT'),
    db: config.get('REDIS_DB'),
    enableOfflineQueue: false,
    maxRetriesPerRequest: 1,
    commandTimeout: 500,
  });

  let available = true;

  redis.on('error', (error: Error) => {
    if (available) {
      available = false;
      logger.warn(
        `Redis unavailable, serving without cache (${describeError(error)})`,
      );
    }
  });

  redis.on('ready', () => {
    if (!available) {
      available = true;
      logger.log('Redis connection restored');
    }
  });

  return redis;
}
