import { chromium, FullConfig } from '@playwright/test';
import { execSync } from 'child_process';

/**
 * Global setup — runs once before all tests.
 *
 * Pings the API to confirm it's up.
 * Optionally seeds the DB (set SEED_DB=true env var).
 */
async function globalSetup(config: FullConfig): Promise<void> {
  const baseURL = process.env.API_BASE_URL ?? 'http://localhost:8000';

  console.log(`\n🔍 Checking API at ${baseURL}/api/v1/ping ...`);

  // Wait for API to be ready (up to 30s)
  const maxRetries = 15;
  for (let i = 0; i < maxRetries; i++) {
    try {
      const res = await fetch(`${baseURL}/api/v1/ping`);
      if (res.ok) {
        const body = await res.json() as { message: string };
        console.log(`✅ API ready: ${body.message}`);
        break;
      }
    } catch {
      if (i === maxRetries - 1) {
        throw new Error(`API at ${baseURL} is not responding after ${maxRetries * 2}s`);
      }
      console.log(`   Waiting for API... (${i + 1}/${maxRetries})`);
      await new Promise(r => setTimeout(r, 2000));
    }
  }

  // Optional: run migrations + seed on the test DB
  if (process.env.SEED_DB === 'true') {
    console.log('🌱 Seeding test database...');
    try {
      execSync('php artisan migrate:fresh --seed --force --env=testing', {
        cwd:   process.env.APP_ROOT ?? process.cwd() + '/..',
        stdio: 'inherit',
      });
      console.log('✅ Database seeded');
    } catch (e) {
      console.warn('⚠️  DB seed failed (tests may still pass if DB is already set up)');
    }
  }
}

export default globalSetup;
