import { test, expect } from '@playwright/test';
import { ApiClient, makeEmail } from '../lib/api-client';

const state = { token: '', userId: 0, otherToken: '', otherId: 0 };

test.describe.serial('Wallets', () => {
  test('setup — register two users', async ({ request }) => {
    const api = new ApiClient(request);
    const r1 = await api.register({
      name: 'Wallet User', email: makeEmail(),
      password: 'Password123!', password_confirmation: 'Password123!',
      role: 'customer',
    });
    state.token  = r1.token;
    state.userId = r1.user.id;

    const api2 = new ApiClient(request);
    const r2 = await api2.register({
      name: 'Other Wallet User', email: makeEmail(),
      password: 'Password123!', password_confirmation: 'Password123!',
      role: 'customer',
    });
    state.otherToken = r2.token;
    state.otherId    = r2.user.id;

    expect(r1.success).toBe(true);
    expect(r2.success).toBe(true);
  });

  test('GET /wallets/{userId} → 200 or 404 (own wallet)', async ({ request }) => {
    const api = new ApiClient(request);
    api.setToken(state.token);

    const { status } = await api.get(`/wallets/${state.userId}`);
    expect([200, 404]).toContain(status);
  });

  test('GET /wallets/{userId} → 403 user cannot view another users wallet', async ({ request }) => {
    const api = new ApiClient(request);
    api.setToken(state.token);

    const { status } = await api.get(`/wallets/${state.otherId}`);
    expect(status).toBe(403);
  });

  test('GET /wallets/{userId} → 401 without token', async ({ request }) => {
    const api = new ApiClient(request);
    const { status } = await api.get(`/wallets/${state.userId}`);
    expect(status).toBe(401);
  });

  test('GET /wallets/{userId}/transactions → 403 user cannot view another users transactions', async ({ request }) => {
    const api = new ApiClient(request);
    api.setToken(state.token);

    const { status } = await api.get(`/wallets/${state.otherId}/transactions`);
    expect(status).toBe(403);
  });

  test('GET /wallets/{userId}/transactions?type=invalid → 422', async ({ request }) => {
    const api = new ApiClient(request);
    api.setToken(state.token);

    const { status } = await api.get(`/wallets/${state.userId}/transactions`, { type: 'invalid' });
    expect(status).toBe(422);
  });

  test('GET /wallets/{userId}/transactions?type=credit → 200 or 404', async ({ request }) => {
    const api = new ApiClient(request);
    api.setToken(state.token);

    const { status } = await api.get(`/wallets/${state.userId}/transactions`, { type: 'credit' });
    expect([200, 404]).toContain(status);
  });
});
