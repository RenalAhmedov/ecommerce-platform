import { configDefaults, defineConfig } from 'vitest/config';

export default defineConfig({
  test: {
    globals: true,
    include: ['src/**/*.spec.ts'],
    exclude: [...configDefaults.exclude, 'src/**/*.integration.spec.ts'],
    passWithNoTests: true,
  },
});
