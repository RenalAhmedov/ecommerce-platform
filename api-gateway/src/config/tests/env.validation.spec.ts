import { validateEnv } from '../env.validation.js';

describe('validateEnv', () => {
  const required = {
    DB_DATABASE: 'ecommerce',
    DB_USERNAME: 'ecommerce',
    DB_PASSWORD: 'secret',
    JWT_SECRET: 'x'.repeat(32),
    INTERNAL_API_KEY: 'k'.repeat(32),
  };

  it('fills in defaults and converts types', () => {
    expect(validateEnv({ ...required, PORT: '4000' })).toMatchObject({
      NODE_ENV: 'development',
      PORT: 4000,
      JWT_EXPIRES_IN: 3600,
      REDIS_DB: 0,
      RATE_LIMIT_ENABLED: true,
    });
  });

  it('reports every problem at once', () => {
    expect(() => validateEnv({})).toThrow(
      /"DB_DATABASE" is required.*"JWT_SECRET" is required.*"INTERNAL_API_KEY" is required/,
    );
  });

  it.each([
    ['a JWT secret under 32 characters', { JWT_SECRET: 'short' }],
    ['an API key under 32 characters', { INTERNAL_API_KEY: 'short' }],
    [
      'tokens living longer than the admin service keeps its cutoffs',
      { JWT_EXPIRES_IN: '86401' },
    ],
  ])('refuses %s', (_case, override) => {
    expect(() => validateEnv({ ...required, ...override })).toThrow(
      'Config validation error',
    );
  });
});
