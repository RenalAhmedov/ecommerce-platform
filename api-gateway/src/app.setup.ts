import type { INestApplication } from '@nestjs/common';
import { ConfigService } from '@nestjs/config';
import type { CustomStrategy } from '@nestjs/microservices';
import { DocumentBuilder, SwaggerModule } from '@nestjs/swagger';
import helmet from 'helmet';
import type { EnvironmentVariables } from './config/interfaces/environment-variables.interface.js';
import { RedisEventsServer } from './messaging/redis-events.transport.js';
import { createRedisTransportOptions } from './messaging/redis-transport.options.js';

const EXPOSED_HEADERS = [
  'Idempotent-Replayed',
  'Retry-After',
  'X-RateLimit-Limit',
  'X-RateLimit-Remaining',
  'X-RateLimit-Reset',
  'X-Request-Id',
];

export function configureApp(app: INestApplication): void {
  const config = getConfig(app);
  const allowedOrigins = config
    .get('CORS_ORIGINS')
    .split(',')
    .map((origin: string) => origin.trim())
    .filter(Boolean);

  app.setGlobalPrefix('api');
  app.enableShutdownHooks();
  app.use(helmet());
  app.enableCors({
    origin: allowedOrigins.length > 0 ? allowedOrigins : false,
    exposedHeaders: EXPOSED_HEADERS,
  });
}

export function setupApiDocs(app: INestApplication): void {
  const config = new DocumentBuilder()
    .setTitle('E-commerce API Gateway')
    .setDescription(
      'Customer-facing REST API: authentication, product catalogue and orders. ' +
        'Use POST /api/auth/login, then "Authorize" with the returned access token.',
    )
    .setVersion('1.0')
    .addBearerAuth()
    .addApiKey({ type: 'apiKey', in: 'header', name: 'X-Api-Key' }, 'api-key')
    .build();

  SwaggerModule.setup(
    'api/docs',
    app,
    SwaggerModule.createDocument(app, config),
    { jsonDocumentUrl: 'api/docs-json' },
  );
}

export function connectEventListener(app: INestApplication): void {
  app.connectMicroservice<CustomStrategy>({
    strategy: new RedisEventsServer(
      createRedisTransportOptions(getConfig(app)),
    ),
  });
}

function getConfig(
  app: INestApplication,
): ConfigService<EnvironmentVariables, true> {
  return app.get<ConfigService<EnvironmentVariables, true>>(ConfigService);
}
