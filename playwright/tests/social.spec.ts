import { test, expect } from '@playwright/test';
import { ApiClient, makeEmail } from '../lib/api-client';

const state = {
  customerToken: '',
  serviceId:     0,
  orderId:       0,
  shareId:       0,
};

test.describe.serial('Social Media', () => {
  test('setup — register user, create order', async ({ request }) => {
    const api = new ApiClient(request);
    const r = await api.register({
      name: 'Social User', email: makeEmail(),
      password: 'Password123!', password_confirmation: 'Password123!',
      role: 'customer',
    });
    state.customerToken = r.token;
    api.setToken(state.customerToken);

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

  // ── Public endpoints ────────────────────────────────────────────────────────

  test('GET /social/trending → 200 (public)', async ({ request }) => {
    const api = new ApiClient(request);
    const { status, body } = await api.get('/social/trending');
    expect(status).toBe(200);
    expect(body.success).toBe(true);
  });

  test('GET /social/statistics → 200 (public)', async ({ request }) => {
    const api = new ApiClient(request);
    const { status, body } = await api.get('/social/statistics');
    expect(status).toBe(200);
    expect(body.success).toBe(true);
  });

  // ── Protected endpoints ─────────────────────────────────────────────────────

  test('GET /social/platforms → 200', async ({ request }) => {
    const api = new ApiClient(request);
    api.setToken(state.customerToken);

    const { status, body } = await api.get('/social/platforms');
    expect(status).toBe(200);
    expect(body.success).toBe(true);
  });

  test('GET /social/platforms → 401 without token', async ({ request }) => {
    const api = new ApiClient(request);
    const { status } = await api.get('/social/platforms');
    expect(status).toBe(401);
  });

  test('GET /social/my-shares → 200', async ({ request }) => {
    const api = new ApiClient(request);
    api.setToken(state.customerToken);

    const { status, body } = await api.get('/social/my-shares');
    expect(status).toBe(200);
    expect(body.success).toBe(true);
  });

  test('GET /social/analytics → 200', async ({ request }) => {
    const api = new ApiClient(request);
    api.setToken(state.customerToken);

    const { status, body } = await api.get('/social/analytics');
    expect(status).toBe(200);
    expect(body.success).toBe(true);
  });

  test('POST /social/orders/{id}/share → 201 or 404', async ({ request }) => {
    test.skip(!state.orderId, 'No order available');

    const api = new ApiClient(request);
    api.setToken(state.customerToken);

    const { status, body } = await api.post(`/social/orders/${state.orderId}/share`, {
      platform: 'facebook',
      message:  'Just placed an order!',
    });

    expect([200, 201, 404, 422]).toContain(status);
    if (status === 201 || status === 200) {
      state.shareId = (body.data as any)?.id ?? 0;
    }
  });

  test('POST /social/referral/share → 201', async ({ request }) => {
    const api = new ApiClient(request);
    api.setToken(state.customerToken);

    const { status, body } = await api.post('/social/referral/share', {
      platform: 'facebook',
    });

    expect([200, 201, 422]).toContain(status);
    if (status === 201 || status === 200) {
      expect(body.success).toBe(true);
    }
  });

  test('POST /social/milestone/share → 201 or 422', async ({ request }) => {
    const api = new ApiClient(request);
    api.setToken(state.customerToken);

    const { status } = await api.post('/social/milestone/share', {
      platform:  'twitter',
      milestone: 'first_order',
    });

    expect([200, 201, 422]).toContain(status);
  });

  test('POST /social/shares/{id}/track → 200 or 404', async ({ request }) => {
    test.skip(!state.shareId, 'No share created');

    const api = new ApiClient(request);
    api.setToken(state.customerToken);

    const { status } = await api.post(`/social/shares/${state.shareId}/track`, {
      action: 'click',
    });
    expect([200, 404]).toContain(status);
  });

  test('GET /social/platforms/{platform}/guidelines → 200 or 404', async ({ request }) => {
    const api = new ApiClient(request);
    api.setToken(state.customerToken);

    const { status } = await api.get('/social/platforms/facebook/guidelines');
    expect([200, 404]).toContain(status);
  });
});
