import { existsSync } from 'node:fs';
import { DataSource } from 'typeorm';
import { createDataSourceOptions } from './database.config.js';

if (existsSync('.env')) {
  process.loadEnvFile('.env');
}

export default new DataSource(createDataSourceOptions());
