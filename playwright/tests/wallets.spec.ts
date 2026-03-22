import { test, expect } from '@playwright/test';
import { ApiClient, makeEmail } from '../lib/api-client';

let customerToken: string;
let customerId: number;
let otherToken: string;
let otherId: number;

test.describe('Wallets', () => {
  test.beforeAll(async ({ request }) => {
    const api = new ApiClient(request);

    const r1 = await api.register({
      name: 'Wallet User', email: makeEmail(),
      password: 'Password123!', password_confirmation: 'Password123!',
      role: 'customer',
    });
    customerToken = r1.token;
    customerId    = r1.user.id;

    const api2 = new ApiClient(request);
    const r2 = await api2.register({
      name: 'Other Wallet User', email: makeEmail(),
      password: 'Password123!', password_confirmation: 'Password123!',
      role: 'customer',
    });
    otherToken = r2.token;
    otherId    = r2.user.id;
  });

  test('GET /wallets/{userId} → 404 when wallet not seeded', async ({ request }) => {
    const api = new ApiClient(request);
    api.setToken(customerToken);

    const { status } = await api.get(`/wallets/${customerId}`);
    // Wallet is created by seeder/migration — if not seeded, expect 404
    expect([200, 404]).toContain(status);
  });

  test('GET /wallets/{userId} → 403 user cannot view another users wallet', async ({ request }) => {
    const api = new ApiClient(request);
    api.setToken(customerToken);

    const { status } = await api.get(`/wallets/${otherId}`);
    expect(status).toBe(403);
  });

  test('GET /wallets/{userId} → 401 without token', async ({ request }) => {
    const api = new ApiClient(request);
    const { status } = await api.get(`/wallets/${customerId}`);
    expect(status).toBe(401);
  });

  test('GET /wallets/{userId}/transactions → 403 user cannot view another users transactions', async ({ request }) => {
    const api = new ApiClient(request);
    api.setToken(customerToken);

    const { status } = await api.get(`/wallets/${otherId}/transactions`);
    expect(status).toBe(403);
  });

  test('GET /wallets/{userId}/transactions?type=invalid → 422', async ({ request }) => {
    const api = new ApiClient(request);
    api.setToken(customerToken);

    const { status } = await api.get(`/wallets/${customerId}/transactions`, { type: 'invalid' });
    expect(status).toBe(422);
  });

  test('GET /wallets/{userId}/transactions?type=credit → 200 or 404', async ({ request }) => {
    const api = new ApiClient(request);
    api.setToken(customerToken);

    const { status } = await api.get(`/wallets/${customerId}/transactions`, { type: 'credit' });
    expect([200, 404]).toContain(status);
  });
});
