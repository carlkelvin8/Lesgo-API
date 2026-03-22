import { test, expect } from '@playwright/test';
import { ApiClient, makeEmail, assertPaginated } from '../lib/api-client';

const BASE = process.env.API_BASE_URL ?? 'http://localhost:8000';

let driverToken: string;
let driverProfileId: number;
let adminToken: string;

test.describe('Drivers — Registration (public)', () => {
  test('POST /drivers/register → 201 creates driver + profile', async ({ request }) => {
    const api = new ApiClient(request);
    const email = makeEmail();

    const { status, body } = await api.post('/drivers/register', {
      name:                  'Pedro Santos',
      email,
      password:              'Password123!',
      password_confirmation: 'Password123!',
      phone_number:          '+639181234567',
      license_number:        'N01-23-456789',
    });

    expect(status).toBe(201);
    expect(body.success).toBe(true);
    expect((body.data as any).driver_profile).toBeDefined();
    expect((body.data as any).driver_profile.status).toBe('pending');

    // Login as this driver for subsequent tests
    const loginRes = await api.login(email, 'Password123!');
    driverToken = loginRes.token;
    driverProfileId = (body.data as any).driver_profile.id;
  });

  test('POST /drivers/register → 422 missing license_number', async ({ request }) => {
    const api = new ApiClient(request);

    const { status } = await api.post('/drivers/register', {
      name:                  'No License',
      email:                 makeEmail(),
      password:              'Password123!',
      password_confirmation: 'Password123!',
      phone_number:          '+639181234567',
    });

    expect(status).toBe(422);
  });
});

test.describe('Drivers — Protected endpoints', () => {
  test.beforeAll(async ({ request }) => {
    // Ensure we have an admin token
    const api = new ApiClient(request);
    const adminEmail = makeEmail();

    // Register admin via direct DB seeding isn't possible here,
    // so we register a customer and rely on the admin token from env if set.
    // In CI, set ADMIN_EMAIL / ADMIN_PASSWORD env vars pointing to a seeded admin.
    const adminEmailEnv = process.env.ADMIN_EMAIL;
    const adminPassEnv  = process.env.ADMIN_PASSWORD;

    if (adminEmailEnv && adminPassEnv) {
      const res = await api.login(adminEmailEnv, adminPassEnv);
      adminToken = res.token;
    }
  });

  test('GET /drivers → 200 for driver (sees own profile)', async ({ request }) => {
    const api = new ApiClient(request);
    api.setToken(driverToken);

    const { status, body } = await api.get('/drivers');

    expect(status).toBe(200);
    assertPaginated(body);
  });

  test('GET /drivers → 401 without token', async ({ request }) => {
    const api = new ApiClient(request);
    const { status } = await api.get('/drivers');
    expect(status).toBe(401);
  });

  test('GET /drivers/{id} → 200 driver can view own profile', async ({ request }) => {
    const api = new ApiClient(request);
    api.setToken(driverToken);

    const { status, body } = await api.get(`/drivers/${driverProfileId}`);

    expect(status).toBe(200);
    expect((body.data as any).id).toBe(driverProfileId);
  });

  test('PATCH /drivers/{id}/location → 200 driver updates own location', async ({ request }) => {
    const api = new ApiClient(request);
    api.setToken(driverToken);

    const { status, body } = await api.patch(`/drivers/${driverProfileId}/location`, {
      last_latitude:  14.5995,
      last_longitude: 120.9842,
    });

    expect(status).toBe(200);
    expect(body.success).toBe(true);
  });

  test('PATCH /drivers/{id}/location → 422 with invalid coordinates', async ({ request }) => {
    const api = new ApiClient(request);
    api.setToken(driverToken);

    const { status } = await api.patch(`/drivers/${driverProfileId}/location`, {
      last_latitude:  'not-a-number',
      last_longitude: 120.9842,
    });

    expect(status).toBe(422);
  });

  test('PATCH /drivers/{id}/status → 403 driver cannot change own status', async ({ request }) => {
    const api = new ApiClient(request);
    api.setToken(driverToken);

    const { status } = await api.patch(`/drivers/${driverProfileId}/status`, {
      status: 'active',
    });

    // Drivers cannot self-promote — only admin/partner_admin can
    expect([403, 422]).toContain(status);
  });
});
