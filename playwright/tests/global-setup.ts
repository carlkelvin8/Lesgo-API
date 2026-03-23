import type { FullConfig } from '@playwright/test';
import { execSync } from 'child_process';
import path from 'path';

const LARAVEL_ROOT = path.resolve(__dirname, '..', '..');

async function globalSetup(_config: FullConfig): Promise<void> {
  // webServer in playwright.config.ts already ensures the API is up.
  // This file only handles optional DB seeding.
  console.log('\n✅ Global setup — API readiness handled by webServer config\n');

  if (process.env.SEED_DB === 'true') {
    const appRoot = process.env.APP_ROOT ?? LARAVEL_ROOT;
    console.log(`🌱 Seeding DB at ${appRoot} ...`);
    try {
      execSync('php artisan migrate:fresh --seed --force', {
        cwd:   appRoot,
        stdio: 'inherit',
        env:   { ...process.env, APP_ENV: 'testing' },
      });
      console.log('✅ Database seeded\n');
    } catch {
      console.warn('⚠️  DB seed failed — continuing anyway\n');
    }
  }
}

export default globalSetup;
