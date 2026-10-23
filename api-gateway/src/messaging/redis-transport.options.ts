import type { ConfigService } from '@nestjs/config';
import type { RedisOptions } from '@nestjs/microservices';
import type { EnvironmentVariables } from '../config/interfaces/environment-variables.interface.js';

export function createRedisTransportOptions(
  config: ConfigService<EnvironmentVariables, true>,
): NonNullable<RedisOptions['options']> {
  return {
    host: config.get('REDIS_HOST'),
    port: config.get('REDIS_PORT'),
    db: config.get('REDIS_DB'),
    retryAttempts: Number.MAX_SAFE_INTEGER,
    retryDelay: 2_000,
  };
}
