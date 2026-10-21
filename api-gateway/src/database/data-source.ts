import { existsSync } from 'node:fs';
import { DataSource } from 'typeorm';
import { validateEnv } from '../config/env.validation.js';
import { createDataSourceOptions } from './database.config.js';

if (existsSync('.env')) {
  process.loadEnvFile('.env');
}

export default new DataSource(
  createDataSourceOptions(validateEnv(process.env)),
);
