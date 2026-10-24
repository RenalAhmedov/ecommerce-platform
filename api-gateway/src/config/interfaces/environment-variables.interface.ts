export interface EnvironmentVariables {
  NODE_ENV: 'development' | 'production' | 'test';
  PORT: number;
  DB_HOST: string;
  DB_PORT: number;
  DB_DATABASE: string;
  DB_USERNAME: string;
  DB_PASSWORD: string;
  DB_LOGGING: boolean;
  JWT_SECRET: string;
  JWT_EXPIRES_IN: number;
  INTERNAL_API_KEY: string;
  REDIS_HOST: string;
  REDIS_PORT: number;
  REDIS_DB: number;
  CACHE_TTL_SECONDS: number;
  ELASTICSEARCH_URL: string;
  MAIL_HOST: string;
  MAIL_PORT: number;
  MAIL_FROM: string;
  RATE_LIMIT_ENABLED: boolean;
  CORS_ORIGINS: string;
  TRUST_PROXY: string;
}
