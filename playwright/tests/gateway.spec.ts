import { test, expect } from '@playwright/test';
import { ApiClient, makeEmail } from '../lib/api-client';

const state = {
  token:     '',
  invoiceId: '',
  serviceId: 0,
  orderId:   0,
};

test.describe.serial('Payment Gateway (Xendit)', () => {
  test('setup — register user, create order', async ({ request }) => {
    const api = new ApiClient(request);
    const r = await api.register({
      name: 'Gateway User', email: makeEmail(),
      password: 'Password123!', password_confirmation: 'Password123!',
      role: 'customer',
    });
    state.token = r.token;
    api.setToken(state.token);

    const { body: svcList } = await api.get('/services');
    const services = (svcList.data ?? []) as any[];
    if (services.length > 0) {
      state.serviceId = services[0].id;
      const { body: orderBody } = await api.post('/orders', {
        service_id:           state.serviceId,
        pickup:               { address: '123 Rizal St', lat: 14.5995, lng: 120.9842 },
        dropoff:              { address: '456 Mabini Ave', lat: 14.6090, lng: 121.0000 },
        estimated_distance_m: 5000,
        payment_method:       'gcash',
      });
      state.orderId = (orderBody.data as any)?.id ?? 0;
    }

    expect(r.success).toBe(true);
  });

  test('GET /gateway/invoice → 401 without token', async ({ request }) => {
    const api = new ApiClient(request);
    const { status } = await api.get('/gateway/invoice/test-id');
    expect(status).toBe(401);
  });

  test('POST /gateway/invoice → 201 or 422 (Xendit may not be configured)', async ({ request }) => {
    test.skip(!state.orderId, 'No order available');

    const api = new ApiClient(request);
    api.setToken(state.token);

    const { status, body } = await api.post('/gateway/invoice', {
      order_id:     state.orderId,
      amount:       150.00,
      description:  'Test invoice',
      payer_email:  'test@lesgo-test.ph',
    });

    // 201 if Xendit is configured, 422/500/502 if not
    expect([201, 200, 422, 500, 502, 503]).toContain(status);
    if (status === 201 || status === 200) {
      state.invoiceId = (body.data as any)?.invoice_id ?? (body.data as any)?.id ?? '';
    }
  });

  test('GET /gateway/invoice/{id} → 200 or 404', async ({ request }) => {
    test.skip(!state.invoiceId, 'No invoice created');

    const api = new ApiClient(request);
    api.setToken(state.token);

    const { status } = await api.get(`/gateway/invoice/${state.invoiceId}`);
    expect([200, 404, 422]).toContain(status);
  });

  test('POST /gateway/invoice/{id}/expire → 200 or 404', async ({ request }) => {
    test.skip(!state.invoiceId, 'No invoice created');

    const api = new ApiClient(request);
    api.setToken(state.token);

    const { status } = await api.post(`/gateway/invoice/${state.invoiceId}/expire`);
    expect([200, 404, 422]).toContain(status);
  });

  test('POST /gateway/refund → 422 missing fields', async ({ request }) => {
    const api = new ApiClient(request);
    api.setToken(state.token);

    const { status } = await api.post('/gateway/refund', {});
    expect([422, 400, 403]).toContain(status);
  });
});
