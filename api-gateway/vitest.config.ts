import { configDefaults, defineConfig } from 'vitest/config';

export default defineConfig({
  // graphql ships a CommonJS and an ES-module build. Node (and so the app) loads
  // the CommonJS one; without this, Vite would load the other for our own files,
  // and graphql refuses to mix types from two copies of itself.
  resolve: { alias: { graphql: 'graphql/index.js' } },
  test: {
    globals: true,
    include: ['src/**/*.spec.ts'],
    exclude: [...configDefaults.exclude, 'src/**/*.integration.spec.ts'],
    passWithNoTests: true,
  },
});
