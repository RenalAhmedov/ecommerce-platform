import { ConfigService } from '@nestjs/config';
import { NestFactory } from '@nestjs/core';
import type { CustomStrategy } from '@nestjs/microservices';
import type { Redis } from 'ioredis';
import { AppModule } from './app.module.js';
import { REDIS_CLIENT } from './cache/constants/cache.constants.js';
import type { EnvironmentVariables } from './config/interfaces/environment-variables.interface.js';
import { RedisEventsServer } from './messaging/redis-events.transport.js';
import { createRedisTransportOptions } from './messaging/redis-transport.options.js';
import { startEventListener } from './messaging/start-event-listener.js';

async function bootstrap() {
  const app = await NestFactory.create(AppModule);
  const config =
    app.get<ConfigService<EnvironmentVariables, true>>(ConfigService);

  app.setGlobalPrefix('api');
  app.enableShutdownHooks();
  app.connectMicroservice<CustomStrategy>({
    strategy: new RedisEventsServer(createRedisTransportOptions(config)),
  });

  await app.listen(config.get('PORT'));
  void startEventListener(app, app.get<Redis>(REDIS_CLIENT));
}
await bootstrap();
