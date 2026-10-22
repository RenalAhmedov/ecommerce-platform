import type { INestApplication } from '@nestjs/common';
import { ConfigService } from '@nestjs/config';
import type { CustomStrategy } from '@nestjs/microservices';
import type { EnvironmentVariables } from './config/interfaces/environment-variables.interface.js';
import { RedisEventsServer } from './messaging/redis-events.transport.js';
import { createRedisTransportOptions } from './messaging/redis-transport.options.js';

export function configureApp(app: INestApplication): void {
  app.setGlobalPrefix('api');
  app.enableShutdownHooks();
}

export function connectEventListener(app: INestApplication): void {
  const config =
    app.get<ConfigService<EnvironmentVariables, true>>(ConfigService);

  app.connectMicroservice<CustomStrategy>({
    strategy: new RedisEventsServer(createRedisTransportOptions(config)),
  });
}
