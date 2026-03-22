import { test, expect } from '@playwright/test';
import { ApiClient, assertPaginated } from '../lib/api-client';

test.describe('Services — Public endpoints', () => {
  test('GET /services → 200 paginated list (no auth required)', async ({ request }) => {
    const api = new ApiClient(request);
    const { status, body } = await api.get('/services');

    expect(status).toBe(200);
    assertPaginated(body);
  });

  test('GET /services?only_active=true → 200', async ({ request }) => {
    const api = new ApiClient(request);
    const { status, body } = await api.get('/services', { only_active: 'true' });

    expect(status).toBe(200);
    expect(body.success).toBe(true);
  });

  test('GET /services/{id} → 200 for existing service', async ({ request }) => {
    const api = new ApiClient(request);

    // First get the list to find a real ID
    const { body: list } = await api.get('/services');
    const items = list.data as any[];

    if (items.length === 0) {
      test.skip(true, 'No services seeded — skipping show test');
      return;
    }

    const id = items[0].id;
    const { status, body } = await api.get(`/services/${id}`);

    expect(status).toBe(200);
    expect(body.success).toBe(true);
    expect((body.data as any).id).toBe(id);
  });

  test('GET /services/99999 → 404', async ({ request }) => {
    const api = new ApiClient(request);
    const { status } = await api.get('/services/99999');
    expect(status).toBe(404);
  });
});
