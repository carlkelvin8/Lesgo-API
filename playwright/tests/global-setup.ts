import type { FullConfig } from '@playwright/test';
import { execSync } from 'child_process';
import path from 'path';

const LARAVEL_ROOT = path.resolve(__dirname, '..', '..');

/**
 * Global setup — runs once before all tests.
 *
 * NOTE: Playwright's `webServer` config already handles starting
 * `php artisan serve` and waiting for it to be ready.
 * This file only handles optional DB seeding.
 *
 * Environment variables:
 *   API_BASE_URL   — target API (default: http://127.0.0.1:8000)
 *   SEED_DB=true   — run migrate:fresh --seed before tests
 *   APP_ROOT       — override Laravel root path
 */
async function globalSetup(config: FullConfig): Promise<void> {
  const baseURL = process.env.API_BASE_URL ?? 'http://127.0.0.1:8000';

  // Quick connectivity check — non-fatal, webServer already handles startup
  console.log(`\n🔍 Verifying API at ${baseURL}/api/v1/ping ...`);
  try {
    const res = await fetch(`${baseURL}/api/v1/ping`);
    if (res.ok) {
      const body = await res.json() as { message: string };
      console.log(`✅ API ready: ${body.message}`);
    } else {
      console.warn(`⚠️  Ping returned HTTP ${res.status} — tests may fail`);
    }
  } catch (err) {
    // webServer hasn't started yet or Docker isn't up — warn, don't throw
    console.warn(`⚠️  Could not reach ${baseURL} — ensure the API is running.`);
    console.warn(`   Run: php artisan serve  OR  docker compose up -d`);
  }

  // Optional DB seed
  if (process.env.SEED_DB === 'true') {
    const appRoot = process.env.APP_ROOT ?? LARAVEL_ROOT;
    console.log(`\n🌱 Seeding DB at ${appRoot} ...`);
    try {
      execSync('php artisan migrate:fresh --seed --force', {
        cwd:   appRoot,
        stdio: 'inherit',
        env:   { ...process.env, APP_ENV: 'testing' },
      });
      console.log('✅ Database seeded\n');
    } catch {
      console.warn('⚠️  DB seed failed — tests may still pass if DB is already set up\n');
    }
  }
}

export default globalSetup;
