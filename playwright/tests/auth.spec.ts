import { test, expect } from '@playwright/test';
import { ApiClient, makeEmail, assertSuccess, assertError } from '../lib/api-client';

// ── Shared state across tests in this file ────────────────────────────────────
let customerEmail: string;
let customerPassword = 'Password123!';
let customerToken: string;

test.describe('Auth — Register', () => {
  test('POST /auth/register → 201 with token and user', async ({ request }) => {
    const api = new ApiClient(request);
    customerEmail = makeEmail();

    const res = await api.register({
      name:                  'Juan dela Cruz',
      email:                 customerEmail,
      password:              customerPassword,
      password_confirmation: customerPassword,
      role:                  'customer',
      phone_number:          '+639171234567',
    });

    expect(res.success).toBe(true);
    expect(typeof res.token).toBe('string');
    expect(res.token.length).toBeGreaterThan(10);
    expect(res.user.role).toBe('customer');
    expect(res.user.email).toBe(customerEmail);

    customerToken = res.token;
  });

  test('POST /auth/register → 422 on duplicate email', async ({ request }) => {
    const api = new ApiClient(request);

    const res = await request.post(`${process.env.API_BASE_URL ?? 'http://localhost:8000'}/api/v1/auth/register`, {
      data: {
        name:                  'Duplicate',
        email:                 customerEmail,
        password:              customerPassword,
        password_confirmation: customerPassword,
        role:                  'customer',
      },
    });

    expect(res.status()).toBe(422);
    const body = await res.json();
    expect(body.success).toBe(false);
  });

  test('POST /auth/register → 422 when role is admin', async ({ request }) => {
    const api = new ApiClient(request);

    const res = await request.post(`${process.env.API_BASE_URL ?? 'http://localhost:8000'}/api/v1/auth/register`, {
      data: {
        name:                  'Hacker',
        email:                 makeEmail(),
        password:              customerPassword,
        password_confirmation: customerPassword,
        role:                  'admin',
      },
    });

    expect(res.status()).toBe(422);
  });

  test('POST /auth/register → 422 when password_confirmation missing', async ({ request }) => {
    const res = await request.post(`${process.env.API_BASE_URL ?? 'http://localhost:8000'}/api/v1/auth/register`, {
      data: {
        name:     'Test',
        email:    makeEmail(),
        password: customerPassword,
        role:     'customer',
      },
    });

    expect(res.status()).toBe(422);
  });
});

test.describe('Auth — Login', () => {
  test('POST /auth/login → 200 with token', async ({ request }) => {
    const api = new ApiClient(request);
    const res = await api.login(customerEmail, customerPassword);

    expect(res.success).toBe(true);
    expect(typeof res.token).toBe('string');
    customerToken = res.token;
  });

  test('POST /auth/login → 401 with wrong password', async ({ request }) => {
    const res = await request.post(`${process.env.API_BASE_URL ?? 'http://localhost:8000'}/api/v1/auth/login`, {
      data: { email: customerEmail, password: 'wrongpassword' },
    });

    expect(res.status()).toBe(401);
    const body = await res.json();
    expect(body.success).toBe(false);
  });

  test('POST /auth/login → 401 with unknown email', async ({ request }) => {
    const res = await request.post(`${process.env.API_BASE_URL ?? 'http://localhost:8000'}/api/v1/auth/login`, {
      data: { email: 'nobody@nowhere.com', password: 'password' },
    });

    expect(res.status()).toBe(401);
  });
});

test.describe('Auth — Protected endpoints', () => {
  test('GET /auth/me → 200 returns current user', async ({ request }) => {
    const api = new ApiClient(request);
    api.setToken(customerToken);

    const { status, body } = await api.get('/auth/me');

    expect(status).toBe(200);
    expect(body.user).toBeDefined();
    expect((body as any).user.email).toBe(customerEmail);
  });

  test('GET /auth/me → 401 without token', async ({ request }) => {
    const api = new ApiClient(request);
    const { status } = await api.get('/auth/me');
    expect(status).toBe(401);
  });

  test('PUT /auth/me → 200 updates name', async ({ request }) => {
    const api = new ApiClient(request);
    api.setToken(customerToken);

    const { status, body } = await api.put('/auth/me', { name: 'Updated Name' });

    expect(status).toBe(200);
    expect((body as any).user.name).toBe('Updated Name');
  });

  test('POST /auth/fcm-token → 200 registers FCM token', async ({ request }) => {
    const api = new ApiClient(request);
    api.setToken(customerToken);

    const { status, body } = await api.post('/auth/fcm-token', {
      fcm_token: 'test-fcm-device-token-abc123',
    });

    expect(status).toBe(200);
    expect(body.success).toBe(true);
  });

  test('POST /auth/logout → 200', async ({ request }) => {
    const api = new ApiClient(request);
    api.setToken(customerToken);

    const { status, body } = await api.post('/auth/logout');

    expect(status).toBe(200);
    expect(body.success).toBe(true);
  });
});
