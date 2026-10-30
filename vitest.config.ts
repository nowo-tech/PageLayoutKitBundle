import { defineConfig } from 'vitest/config';

/**
 * Vitest configuration for the Page Layout Kit Bundle TypeScript sources.
 * Runs every `*.test.ts` under src/Resources/assets with jsdom and enforces the coverage gate.
 */
export default defineConfig({
  test: {
    environment: 'jsdom',
    globals: true,
    include: ['src/Resources/assets/**/*.test.ts'],
    coverage: {
      provider: 'v8',
      reporter: ['text', 'text-summary', 'html'],
      reportsDirectory: './coverage-ts',
      include: ['src/Resources/assets/src/**/*.ts'],
      exclude: ['src/Resources/assets/**/*.test.ts', '**/node_modules/**'],
      thresholds: {
        lines: 100,
        functions: 100,
        branches: 100,
        statements: 100,
      },
    },
  },
  resolve: {
    extensions: ['.ts'],
  },
});
