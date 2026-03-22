import { defineConfig } from '@playwright/test';

export default defineConfig({
  testDir: './tests',
  timeout: 30_000,
  retries: 1,
  workers: 1, // serial — API tests share state (DB seeding)

  globalSetup: './tests/global-setup.ts',

  reporter: [
    ['list'],
    ['html', { outputFolder: 'playwright-report', open: 'never' }],
    ['json', { outputFile: 'playwright-report/results.json' }],
  ],

  use: {
    baseURL: process.env.API_BASE_URL ?? 'http://localhost:8000',
    extraHTTPHeaders: {
      'Accept': 'application/json',
      'Content-Type': 'application/json',
    },
  },

  // Run all suites in this order
  projects: [
    { name: 'auth',          testMatch: 'tests/auth.spec.ts' },
    { name: 'services',      testMatch: 'tests/services.spec.ts' },
    { name: 'drivers',       testMatch: 'tests/drivers.spec.ts' },
    { name: 'orders',        testMatch: 'tests/orders.spec.ts' },
    { name: 'payments',      testMatch: 'tests/payments.spec.ts' },
    { name: 'wallets',       testMatch: 'tests/wallets.spec.ts' },
    { name: 'notifications', testMatch: 'tests/notifications.spec.ts' },
    { name: 'webhooks',      testMatch: 'tests/webhooks.spec.ts' },
  ],
});
