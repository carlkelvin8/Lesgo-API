import { defineConfig } from '@playwright/test';
import path from 'path';

// Support both localhost and 127.0.0.1 — normalize to 127.0.0.1 for webServer
const RAW_URL    = process.env.API_BASE_URL ?? 'http://127.0.0.1:8000';
const BASE_URL   = RAW_URL.replace('localhost', '127.0.0.1');
const LARAVEL_ROOT = path.resolve(__dirname, '..');

export default defineConfig({
  testDir:  './tests',
  timeout:  30_000,
  retries:  0,
  workers:  1,

  globalSetup: './tests/global-setup.ts',

  reporter: [
    ['list'],
    ['html', { outputFolder: 'playwright-report', open: 'never' }],
    ['json', { outputFile: 'playwright-report/results.json' }],
  ],

  use: {
    baseURL: BASE_URL,
    extraHTTPHeaders: {
      Accept:         'application/json',
      'Content-Type': 'application/json',
    },
  },

  /**
   * webServer — auto-starts `php artisan serve` when the API isn't already up.
   * reuseExistingServer: true means Docker / manual serve both work fine.
   */
  webServer: {
    command:             'php artisan serve --host=127.0.0.1 --port=8000',
    cwd:                 LARAVEL_ROOT,
    url:                 'http://127.0.0.1:8000/api/v1/ping',
    reuseExistingServer: true,
    timeout:             120_000,
    stdout:              'ignore',
    stderr:              'ignore',
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
    { name: 'performance',   testMatch: 'tests/performance.spec.ts' },
  ],
});
