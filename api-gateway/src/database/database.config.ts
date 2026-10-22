import { join } from 'node:path';
import type { DataSourceOptions } from 'typeorm';
import type { EnvironmentVariables } from '../config/interfaces/environment-variables.interface.js';
import { ENTITIES } from './entities.js';

type DatabaseEnv = Pick<
  EnvironmentVariables,
  | 'DB_HOST'
  | 'DB_PORT'
  | 'DB_DATABASE'
  | 'DB_USERNAME'
  | 'DB_PASSWORD'
  | 'DB_LOGGING'
>;

export function createDataSourceOptions(env: DatabaseEnv): DataSourceOptions {
  return {
    type: 'postgres',
    host: env.DB_HOST,
    port: env.DB_PORT,
    database: env.DB_DATABASE,
    username: env.DB_USERNAME,
    password: env.DB_PASSWORD,
    entities: ENTITIES,
    migrations: [join(import.meta.dirname, 'migrations', '*.js')],
    parseInt8: true,
    synchronize: false,
    logging: env.DB_LOGGING,
  };
}
