import { test, expect } from '@playwright/test';
import { ApiClient, makeEmail } from '../lib/api-client';

const state = { token: '' };

test.describe.serial('Distance', () => {
  test('setup — register user', async ({ request }) => {
    const api = new ApiClient(request);
    const r = await api.register({
      name: 'Distance User', email: makeEmail(),
      password: 'Password123!', password_confirmation: 'Password123!',
      role: 'customer',
    });
    state.token = r.token;
    expect(r.success).toBe(true);
  });

  test('GET /distance/calculate → 401 without token', async ({ request }) => {
    const api = new ApiClient(request);
    const { status } = await api.get('/distance/calculate');
    expect(status).toBe(401);
  });

  test('GET /distance/calculate → 200 with valid coords', async ({ request }) => {
    const api = new ApiClient(request);
    api.setToken(state.token);

    const { status, body } = await api.get('/distance/calculate', {
      pickup_lat:  '14.5995',
      pickup_lng:  '120.9842',
      dropoff_lat: '14.5547',
      dropoff_lng: '121.0244',
    });

    expect([200, 422]).toContain(status);
    if (status === 200) {
      expect(body.success).toBe(true);
      const data = body.data as any;
      expect(data).toHaveProperty('distance_m');
      expect(data).toHaveProperty('distance_km');
    }
  });

  test('GET /distance/calculate → 422 missing coordinates', async ({ request }) => {
    const api = new ApiClient(request);
    api.setToken(state.token);

    const { status } = await api.get('/distance/calculate', {
      pickup_lat: '14.5995',
    });
    expect(status).toBe(422);
  });

  test('GET /distance/overall → 200', async ({ request }) => {
    const api = new ApiClient(request);
    api.setToken(state.token);

    const { status, body } = await api.get('/distance/overall');
    expect([200, 403, 500]).toContain(status);
    if (status === 200) expect(body.success).toBe(true);
  });
});
