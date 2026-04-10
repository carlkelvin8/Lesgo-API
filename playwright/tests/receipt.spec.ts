import { test, expect } from '@playwright/test';
import { ApiClient, makeEmail } from '../lib/api-client';

const state = {
  customerToken: '',
  otherToken:    '',
  serviceId:     0,
  orderId:       0,
};

test.describe.serial('Receipt', () => {
  test('setup — register users, create order', async ({ request }) => {
    const api = new ApiClient(request);
    const r = await api.register({
      name: 'Receipt User', email: makeEmail(),
      password: 'Password123!', password_confirmation: 'Password123!',
      role: 'customer',
    });
    state.customerToken = r.token;
    api.setToken(state.customerToken);

    const api2 = new ApiClient(request);
    const r2 = await api2.register({
      name: 'Other Receipt User', email: makeEmail(),
      password: 'Password123!', password_confirmation: 'Password123!',
      role: 'customer',
    });
    state.otherToken = r2.token;

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

  test('GET /orders/{id}/receipt → 401 without token', async ({ request }) => {
    test.skip(!state.orderId, 'No order available');

    const api = new ApiClient(request);
    const { status } = await api.get(`/orders/${state.orderId}/receipt`);
    expect(status).toBe(401);
  });

  test('GET /orders/{id}/receipt → 200 or 404 owner can view', async ({ request }) => {
    test.skip(!state.orderId, 'No order available');

    const api = new ApiClient(request);
    api.setToken(state.customerToken);

    const { status, body } = await api.get(`/orders/${state.orderId}/receipt`);
    // 404 if order not completed yet, 200 if receipt exists
    expect([200, 404]).toContain(status);
    if (status === 200) expect(body.success).toBe(true);
  });

  test('GET /orders/{id}/receipt → 403 other user cannot view', async ({ request }) => {
    test.skip(!state.orderId, 'No order available');

    const api = new ApiClient(request);
    api.setToken(state.otherToken);

    const { status } = await api.get(`/orders/${state.orderId}/receipt`);
    expect([403, 404]).toContain(status);
  });

  test('GET /orders/99999/receipt → 404', async ({ request }) => {
    const api = new ApiClient(request);
    api.setToken(state.customerToken);

    const { status } = await api.get('/orders/99999/receipt');
    expect(status).toBe(404);
  });
});
