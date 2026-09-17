import { defineConfig } from 'vitest/config';

export default defineConfig({
  test: {
    environment: 'node',
    globals: true,
    coverage: {
      provider: 'v8',
      reporter: ['text', 'json', 'html', 'lcov'],
      include: ['src/**/*.ts'],
      exclude: [
        'node_modules/',
        'dist/',
        'tests/',
        '**/*.test.ts',
        '**/*.spec.ts',
      ],
      lines: 90,
      functions: 90,
      branches: 90,
      statements: 90,
    },
    include: ['tests/**/*.test.ts'],
  },
});
