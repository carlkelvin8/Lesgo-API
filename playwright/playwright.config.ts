import { defineConfig } from '@playwright/test';
import path from 'path';

const BASE_URL = process.env.API_BASE_URL ?? 'http://127.0.0.1:8000';

// Root of the Laravel project (one level up from /playwright)
const LARAVEL_ROOT = path.resolve(__dirname, '..');

export default defineConfig({
  testDir: './tests',
  timeout: 30_000,
  retries: 1,
  workers: 1, // serial — API tests share state

  globalSetup: './tests/global-setup.ts',

  reporter: [
    ['list'],
    ['html', { outputFolder: 'playwright-report', open: 'never' }],
    ['json', { outputFile: 'playwright-report/results.json' }],
  ],

  use: {
    baseURL: BASE_URL,
    extraHTTPHeaders: {
      'Accept':       'application/json',
      'Content-Type': 'application/json',
    },
  },

  /**
   * webServer — Playwright will start `php artisan serve` automatically
   * when no external API_BASE_URL is provided.
   *
   * If you already have Docker running on :8000, set:
   *   API_BASE_URL=http://localhost:8000 npm test
   * and this block is ignored (reuseExistingServer: true).
   */
  webServer: process.env.API_BASE_URL
    ? undefined
    : {
        command:              'php artisan serve --host=127.0.0.1 --port=8000',
        cwd:                  LARAVEL_ROOT,
        url:                  'http://127.0.0.1:8000/api/v1/ping',
        reuseExistingServer:  true,
        timeout:              60_000,
        stdout:               'pipe',
        stderr:               'pipe',
      },

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
