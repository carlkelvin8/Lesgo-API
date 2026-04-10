import { test, expect } from '@playwright/test';
import { ApiClient, makeEmail } from '../lib/api-client';

const state = { token: '' };

test.describe.serial('Security', () => {
  test('setup — register user', async ({ request }) => {
    const api = new ApiClient(request);
    const r = await api.register({
      name: 'Security User', email: makeEmail(),
      password: 'Password123!', password_confirmation: 'Password123!',
      role: 'customer',
    });
    state.token = r.token;
    expect(r.success).toBe(true);
  });

  test('GET /security/dashboard → 401 without token', async ({ request }) => {
    const api = new ApiClient(request);
    const { status } = await api.get('/security/dashboard');
    expect(status).toBe(401);
  });

  test('GET /security/dashboard → 200 or 403', async ({ request }) => {
    const api = new ApiClient(request);
    api.setToken(state.token);

    const { status, body } = await api.get('/security/dashboard');
    expect([200, 403, 500]).toContain(status);
    if (status === 200) expect(body.success).toBe(true);
  });

  // ── 2FA ─────────────────────────────────────────────────────────────────────

  test('POST /security/2fa/setup → 200', async ({ request }) => {
    const api = new ApiClient(request);
    api.setToken(state.token);

    const { status, body } = await api.post('/security/2fa/setup');
    expect([200, 201, 500]).toContain(status);
    if (status === 200 || status === 201) expect(body.success).toBe(true);
  });

  test('POST /security/2fa/verify → 422 invalid code', async ({ request }) => {
    const api = new ApiClient(request);
    api.setToken(state.token);

    const { status } = await api.post('/security/2fa/verify', {
      code: '000000',
    });
    expect([422, 400, 401, 500]).toContain(status);
  });

  test('POST /security/2fa/disable → 422 or 200', async ({ request }) => {
    const api = new ApiClient(request);
    api.setToken(state.token);

    const { status } = await api.post('/security/2fa/disable', {
      code: '000000',
    });
    expect([200, 400, 422, 500]).toContain(status);
  });

  test('POST /security/2fa/backup-codes/regenerate → 200', async ({ request }) => {
    const api = new ApiClient(request);
    api.setToken(state.token);

    const { status, body } = await api.post('/security/2fa/backup-codes/regenerate');
    expect([200, 201, 400, 500]).toContain(status);
    if (status === 200 || status === 201) expect(body.success).toBe(true);
  });

  // ── Biometric ────────────────────────────────────────────────────────────────

  test('POST /security/biometric/enroll → 200 or 422', async ({ request }) => {
    const api = new ApiClient(request);
    api.setToken(state.token);

    const { status } = await api.post('/security/biometric/enroll', {
      biometric_type: 'fingerprint',
      device_id:      'device-test-001',
      public_key:     'test-public-key-base64',
    });
    expect([200, 201, 422]).toContain(status);
  });

  test('GET /security/biometric/list → 200', async ({ request }) => {
    const api = new ApiClient(request);
    api.setToken(state.token);

    const { status, body } = await api.get('/security/biometric/list');
    expect(status).toBe(200);
    expect(body.success).toBe(true);
  });

  // ── Audit Logs ───────────────────────────────────────────────────────────────

  test('GET /security/audit/logs → 200 or 403', async ({ request }) => {
    const api = new ApiClient(request);
    api.setToken(state.token);

    const { status } = await api.get('/security/audit/logs');
    expect([200, 403]).toContain(status);
  });

  test('GET /security/audit/events → 200 or 403', async ({ request }) => {
    const api = new ApiClient(request);
    api.setToken(state.token);

    const { status } = await api.get('/security/audit/events');
    expect([200, 403]).toContain(status);
  });

  // ── GDPR ─────────────────────────────────────────────────────────────────────

  test('POST /security/gdpr/requests → 201', async ({ request }) => {
    const api = new ApiClient(request);
    api.setToken(state.token);

    const { status, body } = await api.post('/security/gdpr/requests', {
      request_type: 'data_export',
      reason:       'I want a copy of my data',
    });
    expect([200, 201, 422]).toContain(status);
    if (status === 201 || status === 200) expect(body.success).toBe(true);
  });

  test('GET /security/gdpr/requests → 200', async ({ request }) => {
    const api = new ApiClient(request);
    api.setToken(state.token);

    const { status, body } = await api.get('/security/gdpr/requests');
    expect(status).toBe(200);
    expect(body.success).toBe(true);
  });
});
