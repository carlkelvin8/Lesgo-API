import { test, expect } from '@playwright/test';
import { ApiClient, BASE, makeEmail } from '../lib/api-client';

// Shared across all serial tests in this file
const state = {
  email:    '',
  password: 'Password123!',
  token:    '',
};

test.describe.serial('Auth', () => {
  // ── Register ──────────────────────────────────────────────────────────────

  test('POST /auth/register → 201 with token and user', async ({ request }) => {
    const api = new ApiClient(request);
    state.email = makeEmail();

    const res = await api.register({
      name:                  'Juan dela Cruz',
      email:                 state.email,
      password:              state.password,
      password_confirmation: state.password,
      role:                  'customer',
      phone_number:          '+639171234567',
    });

    expect(res.success).toBe(true);
    expect(typeof res.token).toBe('string');
    expect(res.token.length).toBeGreaterThan(10);
    expect(res.user.role).toBe('customer');
    expect(res.user.email).toBe(state.email);

    state.token = res.token;
  });

  test('POST /auth/register → 422 on duplicate email', async ({ request }) => {
    // Register a fresh user then try again with same email
    const api       = new ApiClient(request);
    const dupeEmail = makeEmail();

    await api.register({
      name: 'Original', email: dupeEmail,
      password: state.password, password_confirmation: state.password,
      role: 'customer',
    });

    const res = await request.post(`${BASE}/api/v1/auth/register`, {
      data: {
        name: 'Duplicate', email: dupeEmail,
        password: state.password, password_confirmation: state.password,
        role: 'customer',
      },
    });

    expect(res.status()).toBe(422);
    const body = await res.json();
    expect(body.success).toBe(false);
  });

  test('POST /auth/register → 422 when role is admin', async ({ request }) => {
    const res = await request.post(`${BASE}/api/v1/auth/register`, {
      data: {
        name: 'Hacker', email: makeEmail(),
        password: state.password, password_confirmation: state.password,
        role: 'admin',
      },
    });
    expect(res.status()).toBe(422);
  });

  test('POST /auth/register → 422 when password_confirmation missing', async ({ request }) => {
    const res = await request.post(`${BASE}/api/v1/auth/register`, {
      data: { name: 'Test', email: makeEmail(), password: state.password, role: 'customer' },
    });
    expect(res.status()).toBe(422);
  });

  // ── Login ─────────────────────────────────────────────────────────────────

  test('POST /auth/login → 200 with token', async ({ request }) => {
    const api = new ApiClient(request);
    const res = await api.login(state.email, state.password);

    expect(res.success).toBe(true);
    expect(typeof res.token).toBe('string');
    state.token = res.token;
  });

  test('POST /auth/login → 401 with wrong password', async ({ request }) => {
    const res = await request.post(`${BASE}/api/v1/auth/login`, {
      data: { email: state.email, password: 'wrongpassword' },
    });
    expect(res.status()).toBe(401);
    const body = await res.json();
    expect(body.success).toBe(false);
  });

  test('POST /auth/login → 401 with unknown email', async ({ request }) => {
    const res = await request.post(`${BASE}/api/v1/auth/login`, {
      data: { email: 'nobody@nowhere.com', password: 'password' },
    });
    expect(res.status()).toBe(401);
  });

  // ── Protected ─────────────────────────────────────────────────────────────

  test('GET /auth/me → 200 returns current user', async ({ request }) => {
    const api = new ApiClient(request);
    api.setToken(state.token);

    const { status, body } = await api.get('/auth/me');

    expect(status).toBe(200);
    expect((body as any).user).toBeDefined();
    expect((body as any).user.email).toBe(state.email);
  });

  test('GET /auth/me → 401 without token', async ({ request }) => {
    const api = new ApiClient(request);
    const { status } = await api.get('/auth/me');
    expect(status).toBe(401);
  });

  test('PUT /auth/me → 200 updates name', async ({ request }) => {
    const api = new ApiClient(request);
    api.setToken(state.token);

    const { status, body } = await api.put('/auth/me', { name: 'Updated Name' });

    expect(status).toBe(200);
    expect((body as any).user.name).toBe('Updated Name');
  });

  test('POST /auth/fcm-token → 200 registers FCM token', async ({ request }) => {
    const api = new ApiClient(request);
    api.setToken(state.token);

    const { status, body } = await api.post('/auth/fcm-token', {
      fcm_token: 'test-fcm-device-token-abc123',
    });

    expect(status).toBe(200);
    expect(body.success).toBe(true);
  });

  test('POST /auth/logout → 200', async ({ request }) => {
    const api = new ApiClient(request);
    api.setToken(state.token);

    const { status, body } = await api.post('/auth/logout');

    expect(status).toBe(200);
    expect(body.success).toBe(true);
  });
});
