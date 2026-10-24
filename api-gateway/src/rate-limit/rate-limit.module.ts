import { type ExecutionContext, Module } from '@nestjs/common';
import { ConfigService } from '@nestjs/config';
import { APP_GUARD } from '@nestjs/core';
import { ThrottlerModule } from '@nestjs/throttler';
import type { Redis } from 'ioredis';
import { REDIS_CLIENT } from '../cache/constants/cache.constants.js';
import { httpContextOf } from '../common/utils/http-context.js';
import type { EnvironmentVariables } from '../config/interfaces/environment-variables.interface.js';
import { RATE_LIMITS } from './constants/rate-limit.constants.js';
import { RedisThrottlerStorage } from './redis-throttler.storage.js';
import { UserOrIpThrottlerGuard } from './user-or-ip-throttler.guard.js';

@Module({
  imports: [
    ThrottlerModule.forRootAsync({
      inject: [ConfigService, REDIS_CLIENT],
      useFactory: (
        config: ConfigService<EnvironmentVariables, true>,
        redis: Redis,
      ) => ({
        throttlers: [{ name: 'default', ...RATE_LIMITS.default }],
        storage: new RedisThrottlerStorage(redis),
        errorMessage: 'Too many requests, please try again later',
        skipIf: (context: ExecutionContext) =>
          httpContextOf(context) === null || !config.get('RATE_LIMIT_ENABLED'),
      }),
    }),
  ],
  providers: [{ provide: APP_GUARD, useClass: UserOrIpThrottlerGuard }],
})
export class RateLimitModule {}
