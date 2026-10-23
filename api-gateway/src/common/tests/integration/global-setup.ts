/// <reference types="vite/client" />
import { PostgreSqlContainer } from '@testcontainers/postgresql';
import { RedisContainer } from '@testcontainers/redis';
import { DataSource, type MigrationInterface } from 'typeorm';
import type { TestProject } from 'vitest/node';
import { validateEnv } from '../../../config/env.validation.js';
import { createDataSourceOptions } from '../../../database/database.config.js';

type MigrationClass = new () => MigrationInterface;

declare module 'vitest' {
  export interface ProvidedContext {
    integrationEnv: Record<string, string>;
  }
}

const migrationModules = import.meta.glob<Record<string, MigrationClass>>(
  '../../../database/migrations/*.ts',
  { eager: true },
);

export default async function setup(
  project: TestProject,
): Promise<() => Promise<void>> {
  const [postgres, redis] = await Promise.all([
    new PostgreSqlContainer('postgres:16-alpine')
      .withDatabase('ecommerce_test')
      .start(),
    new RedisContainer('redis:7-alpine').start(),
  ]);

  const env = {
    NODE_ENV: 'test',
    DB_HOST: postgres.getHost(),
    DB_PORT: String(postgres.getPort()),
    DB_DATABASE: postgres.getDatabase(),
    DB_USERNAME: postgres.getUsername(),
    DB_PASSWORD: postgres.getPassword(),
    DB_LOGGING: 'false',
    REDIS_HOST: redis.getHost(),
    REDIS_PORT: String(redis.getPort()),
    REDIS_DB: '0',
    RATE_LIMIT_ENABLED: 'false',
    JWT_SECRET: 'integration-tests-only-secret-0123456789abcdef',
  };

  await runMigrations(validateEnv(env));
  project.provide('integrationEnv', env);

  return async () => {
    await Promise.all([postgres.stop(), redis.stop()]);
  };
}

async function runMigrations(
  env: ReturnType<typeof validateEnv>,
): Promise<void> {
  const dataSource = new DataSource({
    ...createDataSourceOptions(env),
    migrations: Object.values(migrationModules).flatMap((module) =>
      Object.values(module),
    ),
  });

  await dataSource.initialize();

  try {
    await dataSource.runMigrations();
  } finally {
    await dataSource.destroy();
  }
}
