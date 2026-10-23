import { ConfigService } from '@nestjs/config';
import { NestFactory } from '@nestjs/core';
import type { Redis } from 'ioredis';
import { AppModule } from './app.module.js';
import {
  configureApp,
  connectEventListener,
  setupApiDocs,
} from './app.setup.js';
import { REDIS_CLIENT } from './cache/constants/cache.constants.js';
import type { EnvironmentVariables } from './config/interfaces/environment-variables.interface.js';
import { startEventListener } from './messaging/start-event-listener.js';

async function bootstrap() {
  const app = await NestFactory.create(AppModule);
  const config =
    app.get<ConfigService<EnvironmentVariables, true>>(ConfigService);

  configureApp(app);
  connectEventListener(app);

  if (config.get('NODE_ENV') !== 'production') {
    setupApiDocs(app);
  }

  await app.listen(config.get('PORT'));
  void startEventListener(app, app.get<Redis>(REDIS_CLIENT));
}
await bootstrap();
