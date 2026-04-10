import { test, expect } from '@playwright/test';
import { ApiClient } from '../lib/api-client';

test.describe('Ping', () => {
  test('GET /ping → 200 health check', async ({ request }) => {
    const api = new ApiClient(request);
    const { status, body } = await api.get('/ping');

    expect(status).toBe(200);
    expect((body as any).message).toContain('LeSGo API');
    expect((body as any).timestamp).toBeDefined();
    expect((body as any).php_version).toBeDefined();
    expect((body as any).laravel_version).toBeDefined();
  });
});
