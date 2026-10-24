import Joi from 'joi';
import type { EnvironmentVariables } from './interfaces/environment-variables.interface.js';

const envValidationSchema = Joi.object<EnvironmentVariables>({
  NODE_ENV: Joi.string()
    .valid('development', 'production', 'test')
    .default('development'),
  PORT: Joi.number().port().default(3000),

  DB_HOST: Joi.string().default('localhost'),
  DB_PORT: Joi.number().port().default(5432),
  DB_DATABASE: Joi.string().required(),
  DB_USERNAME: Joi.string().required(),
  DB_PASSWORD: Joi.string().required(),
  DB_LOGGING: Joi.boolean().default(false),

  JWT_SECRET: Joi.string().min(32).required(),
  // At most a day: the admin service keeps a user's token cutoff for a day.
  JWT_EXPIRES_IN: Joi.number().integer().positive().max(86_400).default(3600),

  INTERNAL_API_KEY: Joi.string().min(32).required(),

  REDIS_HOST: Joi.string().default('localhost'),
  REDIS_PORT: Joi.number().port().default(6379),
  REDIS_DB: Joi.number().integer().min(0).max(15).default(0),
  CACHE_TTL_SECONDS: Joi.number().integer().positive().default(60),

  // Empty: product search runs on Postgres only.
  ELASTICSEARCH_URL: Joi.string()
    .uri({ scheme: ['http', 'https'] })
    .allow('')
    .default(''),

  MAIL_HOST: Joi.string().default('localhost'),
  MAIL_PORT: Joi.number().port().default(1025),
  MAIL_FROM: Joi.string().default('Shop <no-reply@shop.local>'),

  RATE_LIMIT_ENABLED: Joi.boolean().default(true),
  CORS_ORIGINS: Joi.string().allow('').default(''),
  // Express "trust proxy": whose X-Forwarded-For to believe. The default trusts
  // private networks, where Nginx sits; it then reports the real client IP.
  TRUST_PROXY: Joi.string().default('loopback, linklocal, uniquelocal'),
});

export function validateEnv(
  config: Record<string, unknown>,
): EnvironmentVariables {
  const { error, value } = envValidationSchema.validate(config, {
    allowUnknown: true,
    abortEarly: false,
  });

  if (error) {
    throw new Error(`Config validation error: ${error.message}`);
  }

  return value;
}
