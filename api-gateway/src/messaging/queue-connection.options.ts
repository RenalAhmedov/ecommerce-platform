import type { ConfigService } from '@nestjs/config';
import type { ConnectionOptions } from 'bullmq';
import type { EnvironmentVariables } from '../config/interfaces/environment-variables.interface.js';

export function createQueueConnectionOptions(
  config: ConfigService<EnvironmentVariables, true>,
): ConnectionOptions {
  return {
    host: config.get('REDIS_HOST'),
    port: config.get('REDIS_PORT'),
    db: config.get('REDIS_DB'),
    enableOfflineQueue: false,
    maxRetriesPerRequest: null,
  };
}
