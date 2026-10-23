import { defineConfig } from 'vitest/config';

export default defineConfig({
  test: {
    globals: true,
    include: ['src/**/*.integration.spec.ts'],
    globalSetup: ['src/common/tests/integration/global-setup.ts'],
    setupFiles: ['src/common/tests/integration/setup-env.ts'],
    fileParallelism: false,
    testTimeout: 20_000,
    hookTimeout: 120_000,
  },
});
