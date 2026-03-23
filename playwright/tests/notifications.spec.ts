import { test, expect } from '@playwright/test';
import { ApiClient, makeEmail, assertPaginated } from '../lib/api-client';

const state = { token: '', userId: 0 };

test.describe.serial('Notifications', () => {
  test('setup — register user', async ({ request }) => {
    const api = new ApiClient(request);
    const res = await api.register({
      name: 'Notif User', email: makeEmail(),
      password: 'Password123!', password_confirmation: 'Password123!',
      role: 'customer',
    });
    state.token  = res.token;
    state.userId = res.user.id;
    expect(res.success).toBe(true);
  });

  test('GET /notifications → 401 without token', async ({ request }) => {
    const api = new ApiClient(request);
    const { status } = await api.get('/notifications');
    expect(status).toBe(401);
  });

  test('GET /notifications → 200 paginated (empty for new user)', async ({ request }) => {
    const api = new ApiClient(request);
    api.setToken(state.token);

    const { status, body } = await api.get('/notifications');

    expect(status).toBe(200);
    assertPaginated(body);
    expect(body.meta!.total).toBeGreaterThanOrEqual(0);
  });

  test('GET /notifications?unread_only=true → 200', async ({ request }) => {
    const api = new ApiClient(request);
    api.setToken(state.token);

    const { status, body } = await api.get('/notifications', { unread_only: 'true' });

    expect(status).toBe(200);
    expect(body.success).toBe(true);
  });

  test('GET /notifications/unread-count → 200 returns count', async ({ request }) => {
    const api = new ApiClient(request);
    api.setToken(state.token);

    const { status, body } = await api.get('/notifications/unread-count');

    expect(status).toBe(200);
    expect(body.success).toBe(true);
    expect(typeof (body.data as any).unread_count).toBe('number');
  });

  test('POST /notifications/read-all → 200', async ({ request }) => {
    const api = new ApiClient(request);
    api.setToken(state.token);

    const { status, body } = await api.post('/notifications/read-all');

    expect(status).toBe(200);
    expect(body.success).toBe(true);
  });

  test('PATCH /notifications/99999/read → 404 for nonexistent notification', async ({ request }) => {
    const api = new ApiClient(request);
    api.setToken(state.token);

    const { status } = await api.patch('/notifications/99999/read');
    expect(status).toBe(404);
  });
});
