import { Global, Inject, Module, OnApplicationShutdown } from '@nestjs/common';
import { ConfigService } from '@nestjs/config';
import type { Redis } from 'ioredis';
import { CacheService } from './cache.service.js';
import { REDIS_CLIENT } from './constants/cache.constants.js';
import { createRedisClient } from './redis-client.factory.js';

@Global()
@Module({
  providers: [
    {
      provide: REDIS_CLIENT,
      inject: [ConfigService],
      useFactory: createRedisClient,
    },
    CacheService,
  ],
  exports: [CacheService],
})
export class CacheModule implements OnApplicationShutdown {
  constructor(@Inject(REDIS_CLIENT) private readonly redis: Redis) {}

  onApplicationShutdown(): void {
    this.redis.disconnect();
  }
}
