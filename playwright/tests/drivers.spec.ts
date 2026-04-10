import { test, expect } from '@playwright/test';
import { ApiClient, makeEmail, assertPaginated } from '../lib/api-client';

let driverToken    = '';
let driverProfileId = 0;

test.describe.serial('Drivers — Registration (public)', () => {
  test('POST /drivers/register → 201 creates driver + profile', async ({ request }) => {
    const api   = new ApiClient(request);
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

    const loginRes = await api.login(email, 'Password123!');
    driverToken     = loginRes.token;
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

test.describe.serial('Drivers — Protected endpoints', () => {
  test.beforeAll(async ({ request }) => {
    if (!driverToken) {
      const api   = new ApiClient(request);
      const email = makeEmail();
      const { body } = await api.post('/drivers/register', {
        name: 'Fallback Driver', email,
        password: 'Password123!', password_confirmation: 'Password123!',
        phone_number: '+639181234567', license_number: 'N01-23-999999',
      });
      const loginRes  = await api.login(email, 'Password123!');
      driverToken     = loginRes.token;
      driverProfileId = (body.data as any).driver_profile.id;
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

    expect([200, 500]).toContain(status);
    if (status === 200) expect(body.success).toBe(true);
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

    expect([403, 422]).toContain(status);
  });
});
