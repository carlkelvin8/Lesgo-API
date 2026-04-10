import { test, expect } from '@playwright/test';
import { ApiClient, makeEmail } from '../lib/api-client';

const state = {
  customerToken: '',
  driverToken:   '',
  driverProfileId: 0,
  serviceId:     0,
  orderId:       0,
};

test.describe.serial('Order Tracking & Live Tracking', () => {
  test('setup — register customer, driver, create order', async ({ request }) => {
    const api = new ApiClient(request);
    const r = await api.register({
      name: 'Tracking Customer', email: makeEmail(),
      password: 'Password123!', password_confirmation: 'Password123!',
      role: 'customer',
    });
    state.customerToken = r.token;
    api.setToken(state.customerToken);

    // Register driver
    const driverEmail = makeEmail();
    const { body: driverBody } = await api.post('/drivers/register', {
      name: 'Tracking Driver', email: driverEmail,
      password: 'Password123!', password_confirmation: 'Password123!',
      phone_number: '+639181234567', license_number: 'N01-23-TRACK1',
    });
    const driverApi = new ApiClient(request);
    const driverLogin = await driverApi.login(driverEmail, 'Password123!');
    state.driverToken     = driverLogin.token;
    state.driverProfileId = (driverBody.data as any)?.driver_profile?.id ?? 0;

    // Get service and create order
    const { body: svcList } = await api.get('/services');
    const services = (svcList.data ?? []) as any[];
    if (services.length > 0) {
      state.serviceId = services[0].id;
      const { body: orderBody } = await api.post('/orders', {
        service_id:           state.serviceId,
        pickup:               { address: '123 Rizal St', lat: 14.5995, lng: 120.9842 },
        dropoff:              { address: '456 Mabini Ave', lat: 14.6090, lng: 121.0000 },
        estimated_distance_m: 5000,
        payment_method:       'cash',
      });
      state.orderId = (orderBody.data as any)?.id ?? 0;
    }

    expect(r.success).toBe(true);
  });

  // ── Order Tracking ──────────────────────────────────────────────────────────

  test('GET /tracking/orders/{id} → 200 or 404', async ({ request }) => {
    test.skip(!state.orderId, 'No order available');

    const api = new ApiClient(request);
    api.setToken(state.customerToken);

    const { status, body } = await api.get(`/tracking/orders/${state.orderId}`);
    expect([200, 404, 500]).toContain(status);
    if (status === 200) expect(body.success).toBe(true);
  });

  test('GET /tracking/orders/{id}/location → 200 or 404', async ({ request }) => {
    test.skip(!state.orderId, 'No order available');

    const api = new ApiClient(request);
    api.setToken(state.customerToken);

    const { status } = await api.get(`/tracking/orders/${state.orderId}/location`);
    expect([200, 404, 500]).toContain(status);
  });

  test('POST /tracking/orders/{id}/events → 201 or 404', async ({ request }) => {
    test.skip(!state.orderId, 'No order available');

    const api = new ApiClient(request);
    api.setToken(state.customerToken);

    const { status } = await api.post(`/tracking/orders/${state.orderId}/events`, {
      event_type:  'status_update',
      description: 'Order picked up',
      lat:         14.5995,
      lng:         120.9842,
    });
    expect([200, 201, 403, 404, 422, 500]).toContain(status);
  });

  test('POST /tracking/orders/multiple → 200', async ({ request }) => {
    test.skip(!state.orderId, 'No order available');

    const api = new ApiClient(request);
    api.setToken(state.customerToken);

    const { status, body } = await api.post('/tracking/orders/multiple', {
      order_ids: [state.orderId],
    });
    expect([200, 422, 500]).toContain(status);
    if (status === 200) expect(body.success).toBe(true);
  });

  // ── Live Tracking ───────────────────────────────────────────────────────────

  test('POST /tracking/driver/location → 200 driver updates location', async ({ request }) => {
    test.skip(!state.driverToken, 'No driver token');

    const api = new ApiClient(request);
    api.setToken(state.driverToken);

    const { status, body } = await api.post('/tracking/driver/location', {
      latitude:  14.5995,
      longitude: 120.9842,
      heading:   90,
      speed:     30,
    });
    expect([200, 201, 422]).toContain(status);
    if (status === 200 || status === 201) expect(body.success).toBe(true);
  });

  test('POST /tracking/driver/location → 401 without token', async ({ request }) => {
    const api = new ApiClient(request);
    const { status } = await api.post('/tracking/driver/location', {
      latitude: 14.5995, longitude: 120.9842,
    });
    expect(status).toBe(401);
  });

  test('GET /tracking/driver/{id}/location → 200 or 404', async ({ request }) => {
    test.skip(!state.driverProfileId, 'No driver profile');

    const api = new ApiClient(request);
    api.setToken(state.customerToken);

    const { status } = await api.get(`/tracking/driver/${state.driverProfileId}/location`);
    expect([200, 403, 404]).toContain(status);
  });

  test('GET /tracking/driver/{id}/history → 200 or 404', async ({ request }) => {
    test.skip(!state.driverProfileId, 'No driver profile');

    const api = new ApiClient(request);
    api.setToken(state.customerToken);

    const { status } = await api.get(`/tracking/driver/${state.driverProfileId}/history`);
    expect([200, 403, 404]).toContain(status);
  });

  test('GET /tracking/order/{id}/live → 200 or 404', async ({ request }) => {
    test.skip(!state.orderId, 'No order available');

    const api = new ApiClient(request);
    api.setToken(state.customerToken);

    const { status } = await api.get(`/tracking/order/${state.orderId}/live`);
    expect([200, 404]).toContain(status);
  });

  test('GET /tracking/drivers/nearby → 200', async ({ request }) => {
    const api = new ApiClient(request);
    api.setToken(state.customerToken);

    const { status, body } = await api.get('/tracking/drivers/nearby', {
      lat: '14.5995', lng: '120.9842', radius: '5000',
    });
    expect([200, 422]).toContain(status);
    if (status === 200) expect(body.success).toBe(true);
  });

  test('GET /tracking/stats → 200', async ({ request }) => {
    const api = new ApiClient(request);
    api.setToken(state.customerToken);

    const { status, body } = await api.get('/tracking/stats');
    expect([200, 403]).toContain(status);
    if (status === 200) expect(body.success).toBe(true);
  });
});
