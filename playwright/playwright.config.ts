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
    { name: 'ping',                     testMatch: 'tests/ping.spec.ts' },
    { name: 'auth',                     testMatch: 'tests/auth.spec.ts' },
    { name: 'profile',                  testMatch: 'tests/profile.spec.ts' },
    { name: 'users',                    testMatch: 'tests/users.spec.ts' },
    { name: 'services',                 testMatch: 'tests/services.spec.ts' },
    { name: 'partners',                 testMatch: 'tests/partners.spec.ts' },
    { name: 'drivers',                  testMatch: 'tests/drivers.spec.ts' },
    { name: 'order-estimate',           testMatch: 'tests/order-estimate.spec.ts' },
    { name: 'orders',                   testMatch: 'tests/orders.spec.ts' },
    { name: 'booking',                  testMatch: 'tests/booking.spec.ts' },
    { name: 'receipt',                  testMatch: 'tests/receipt.spec.ts' },
    { name: 'payments',                 testMatch: 'tests/payments.spec.ts' },
    { name: 'gateway',                  testMatch: 'tests/gateway.spec.ts' },
    { name: 'wallets',                  testMatch: 'tests/wallets.spec.ts' },
    { name: 'wallet-validation',        testMatch: 'tests/wallet-validation.spec.ts' },
    { name: 'wallet-validation-frontend', testMatch: 'tests/wallet-validation-frontend.spec.ts' },
    { name: 'integrated-features',      testMatch: 'tests/integrated-features.spec.ts' },
    { name: 'notifications',            testMatch: 'tests/notifications.spec.ts' },
    { name: 'webhooks',                 testMatch: 'tests/webhooks.spec.ts' },
    { name: 'reviews',                  testMatch: 'tests/reviews.spec.ts' },
    { name: 'support',                  testMatch: 'tests/support.spec.ts' },
    { name: 'faq',                      testMatch: 'tests/faq.spec.ts' },
    { name: 'tracking',                 testMatch: 'tests/tracking.spec.ts' },
    { name: 'documents',                testMatch: 'tests/documents.spec.ts' },
    { name: 'social',                   testMatch: 'tests/social.spec.ts' },
    { name: 'geofences',                testMatch: 'tests/geofences.spec.ts' },
    { name: 'realtime',                 testMatch: 'tests/realtime.spec.ts' },
    { name: 'analytics',                testMatch: 'tests/analytics.spec.ts' },
    { name: 'security',                 testMatch: 'tests/security.spec.ts' },
    { name: 'checklist',                testMatch: 'tests/checklist.spec.ts' },
    { name: 'distance',                 testMatch: 'tests/distance.spec.ts' },
    { name: 'performance',              testMatch: 'tests/performance.spec.ts' },
  ],
});
