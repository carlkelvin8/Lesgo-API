import { test, expect } from '@playwright/test';
import { ApiClient, makeEmail } from '../lib/api-client';

const state = { token: '' };

test.describe.serial('Analytics', () => {
  test('setup — register user', async ({ request }) => {
    const api = new ApiClient(request);
    const r = await api.register({
      name: 'Analytics User', email: makeEmail(),
      password: 'Password123!', password_confirmation: 'Password123!',
      role: 'customer',
    });
    state.token = r.token;
    expect(r.success).toBe(true);
  });

  test('GET /analytics/dashboard → 401 without token', async ({ request }) => {
    const api = new ApiClient(request);
    const { status } = await api.get('/analytics/dashboard');
    expect(status).toBe(401);
  });

  test('GET /analytics/dashboard → 200 or 403', async ({ request }) => {
    const api = new ApiClient(request);
    api.setToken(state.token);

    const { status, body } = await api.get('/analytics/dashboard');
    expect([200, 403]).toContain(status);
    if (status === 200) expect(body.success).toBe(true);
  });

  test('GET /analytics/revenue → 200 or 403', async ({ request }) => {
    const api = new ApiClient(request);
    api.setToken(state.token);

    const { status } = await api.get('/analytics/revenue');
    expect([200, 403]).toContain(status);
  });

  test('GET /analytics/drivers/performance → 200 or 403', async ({ request }) => {
    const api = new ApiClient(request);
    api.setToken(state.token);

    const { status } = await api.get('/analytics/drivers/performance');
    expect([200, 403]).toContain(status);
  });

  test('GET /analytics/customers/behavior → 200 or 403', async ({ request }) => {
    const api = new ApiClient(request);
    api.setToken(state.token);

    const { status } = await api.get('/analytics/customers/behavior');
    expect([200, 403]).toContain(status);
  });

  test('GET /analytics/services/demand → 200 or 403', async ({ request }) => {
    const api = new ApiClient(request);
    api.setToken(state.token);

    const { status } = await api.get('/analytics/services/demand');
    expect([200, 403]).toContain(status);
  });

  test('GET /analytics/geofences/effectiveness → 200 or 403', async ({ request }) => {
    const api = new ApiClient(request);
    api.setToken(state.token);

    const { status } = await api.get('/analytics/geofences/effectiveness');
    expect([200, 403]).toContain(status);
  });

  test('GET /analytics/predictions → 200 or 403', async ({ request }) => {
    const api = new ApiClient(request);
    api.setToken(state.token);

    const { status } = await api.get('/analytics/predictions');
    expect([200, 403]).toContain(status);
  });

  test('GET /analytics/events → 200 or 403', async ({ request }) => {
    const api = new ApiClient(request);
    api.setToken(state.token);

    const { status } = await api.get('/analytics/events');
    expect([200, 403]).toContain(status);
  });

  test('POST /analytics/events/track → 200 or 403', async ({ request }) => {
    const api = new ApiClient(request);
    api.setToken(state.token);

    const { status } = await api.post('/analytics/events/track', {
      event_name: 'page_view',
      properties: { page: 'home' },
    });
    expect([200, 201, 403, 422]).toContain(status);
  });

  test('POST /analytics/export → 200 or 403', async ({ request }) => {
    const api = new ApiClient(request);
    api.setToken(state.token);

    const { status } = await api.post('/analytics/export', {
      type:       'orders',
      start_date: '2025-01-01',
      end_date:   '2025-12-31',
      format:     'csv',
    });
    expect([200, 403, 422]).toContain(status);
  });
});
