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
}
