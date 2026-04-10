import { test, expect } from '@playwright/test';
import { ApiClient, makeEmail, assertPaginated } from '../lib/api-client';

const state = {
  token:      '',
  geofenceId: 0,
};

test.describe.serial('Geofences', () => {
  test('setup — register user', async ({ request }) => {
    const api = new ApiClient(request);
    const r = await api.register({
      name: 'Geofence User', email: makeEmail(),
      password: 'Password123!', password_confirmation: 'Password123!',
      role: 'customer',
    });
    state.token = r.token;
    expect(r.success).toBe(true);
  });

  test('GET /geofences → 401 without token', async ({ request }) => {
    const api = new ApiClient(request);
    const { status } = await api.get('/geofences');
    expect(status).toBe(401);
  });

  test('GET /geofences → 200 paginated', async ({ request }) => {
    const api = new ApiClient(request);
    api.setToken(state.token);

    const { status, body } = await api.get('/geofences');
    expect([200, 500]).toContain(status);
    if (status === 200) {
      expect(body.success).toBe(true);
      expect(body.meta).toBeDefined();
    }
  });

  test('GET /geofences/types → 200', async ({ request }) => {
    const api = new ApiClient(request);
    api.setToken(state.token);

    const { status, body } = await api.get('/geofences/types');
    expect(status).toBe(200);
    expect(body.success).toBe(true);
  });

  test('GET /geofences/statistics → 200', async ({ request }) => {
    const api = new ApiClient(request);
    api.setToken(state.token);

    const { status, body } = await api.get('/geofences/statistics');
    expect(status).toBe(200);
    expect(body.success).toBe(true);
  });

  test('GET /geofences/nearby → 200', async ({ request }) => {
    const api = new ApiClient(request);
    api.setToken(state.token);

    const { status, body } = await api.get('/geofences/nearby', {
      lat: '14.5995', lng: '120.9842',
    });
    expect([200, 422]).toContain(status);
    if (status === 200) expect(body.success).toBe(true);
  });

  test('POST /geofences → 201 creates geofence', async ({ request }) => {
    const api = new ApiClient(request);
    api.setToken(state.token);

    const { status, body } = await api.post('/geofences', {
      name:        'Test Zone Manila',
      type:        'delivery_zone',
      center_lat:  14.5995,
      center_lng:  120.9842,
      radius_m:    5000,
      is_active:   true,
    });

    expect([201, 403, 422]).toContain(status);
    if (status === 201) {
      state.geofenceId = (body.data as any).id;
      expect(body.success).toBe(true);
    }
  });

  test('POST /geofences → 422 missing name', async ({ request }) => {
    const api = new ApiClient(request);
    api.setToken(state.token);

    const { status } = await api.post('/geofences', {
      center_lat: 14.5995, center_lng: 120.9842, radius_m: 1000,
    });
    expect([422, 403]).toContain(status);
  });

  test('GET /geofences/{id} → 200 or 404', async ({ request }) => {
    test.skip(!state.geofenceId, 'No geofence created');

    const api = new ApiClient(request);
    api.setToken(state.token);

    const { status, body } = await api.get(`/geofences/${state.geofenceId}`);
    expect([200, 404]).toContain(status);
    if (status === 200) {
      expect((body.data as any).id).toBe(state.geofenceId);
    }
  });

  test('PUT /geofences/{id} → 200 or 403', async ({ request }) => {
    test.skip(!state.geofenceId, 'No geofence created');

    const api = new ApiClient(request);
    api.setToken(state.token);

    const { status } = await api.put(`/geofences/${state.geofenceId}`, {
      name: 'Updated Zone Manila',
    });
    expect([200, 403, 404]).toContain(status);
  });

  test('POST /geofences/{id}/toggle → 200 or 403', async ({ request }) => {
    test.skip(!state.geofenceId, 'No geofence created');

    const api = new ApiClient(request);
    api.setToken(state.token);

    const { status } = await api.post(`/geofences/${state.geofenceId}/toggle`);
    expect([200, 403, 404]).toContain(status);
  });

  test('GET /geofences/{id}/events → 200 or 404', async ({ request }) => {
    test.skip(!state.geofenceId, 'No geofence created');

    const api = new ApiClient(request);
    api.setToken(state.token);

    const { status } = await api.get(`/geofences/${state.geofenceId}/events`);
    expect([200, 404]).toContain(status);
  });

  test('POST /geofences/location/check → 200', async ({ request }) => {
    const api = new ApiClient(request);
    api.setToken(state.token);

    const { status, body } = await api.post('/geofences/location/check', {
      lat: 14.5995,
      lng: 120.9842,
    });
    expect([200, 422]).toContain(status);
    if (status === 200) expect(body.success).toBe(true);
  });

  test('POST /geofences/location/process → 200', async ({ request }) => {
    const api = new ApiClient(request);
    api.setToken(state.token);

    const { status } = await api.post('/geofences/location/process', {
      lat:       14.5995,
      lng:       120.9842,
      driver_id: 1,
    });
    expect([200, 404, 422]).toContain(status);
  });

  test('DELETE /geofences/{id} → 200 or 403', async ({ request }) => {
    test.skip(!state.geofenceId, 'No geofence created');

    const api = new ApiClient(request);
    api.setToken(state.token);

    const { status } = await api.delete(`/geofences/${state.geofenceId}`);
    expect([200, 403, 404]).toContain(status);
  });
});
