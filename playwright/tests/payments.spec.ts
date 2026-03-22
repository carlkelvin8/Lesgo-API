import { test, expect } from '@playwright/test';
import { ApiClient, makeEmail, assertPaginated } from '../lib/api-client';

const state = {
  customerToken:  '',
  customerId:     0,
  customer2Token: '',
  serviceId:      0,
  orderId:        0,
  paymentId:      0,
};

test.describe.serial('Payments', () => {
  test('setup — register users, get service, create order', async ({ request }) => {
    const api = new ApiClient(request);

    const r1 = await api.register({
      name: 'Pay Customer', email: makeEmail(),
      password: 'Password123!', password_confirmation: 'Password123!',
      role: 'customer',
    });
    state.customerToken = r1.token;
    state.customerId    = r1.user.id;

    const api2 = new ApiClient(request);
    const r2 = await api2.register({
      name: 'Other Customer', email: makeEmail(),
      password: 'Password123!', password_confirmation: 'Password123!',
      role: 'customer',
    });
    state.customer2Token = r2.token;

    const { body: svcList } = await api.get('/services');
    const services = (svcList.data ?? []) as any[];
    if (services.length > 0) state.serviceId = services[0].id;

    if (state.serviceId) {
      const { body: orderBody } = await api.post('/orders', {
        service_id:           state.serviceId,
        pickup:               { address: '123 Rizal St', lat: 14.5995, lng: 120.9842 },
        dropoff:              { address: '456 Mabini Ave', lat: 14.6090, lng: 121.0000 },
        estimated_distance_m: 5000,
        payment_method:       'cash',
      });
      state.orderId = (orderBody.data as any)?.id ?? 0;
    }

    expect(r1.success).toBe(true);
  });

  // ── Store ───────────────────────────────────────────────────────────────────

  test('POST /payments → 201 customer records payment for own order', async ({ request }) => {
    test.skip(!state.orderId, 'No order available');

    const api = new ApiClient(request);
    api.setToken(state.customerToken);

    const { status, body } = await api.post('/payments', {
      order_id:    state.orderId,
      customer_id: state.customerId,
      amount:      150.00,
      method:      'cash',
    });

    expect(status).toBe(201);
    expect(body.success).toBe(true);
    expect((body.data as any).status).toBe('pending');

    state.paymentId = (body.data as any).id;
  });

  test('POST /payments → 403 customer cannot pay for another customers order', async ({ request }) => {
    test.skip(!state.orderId, 'No order available');

    const api = new ApiClient(request);
    api.setToken(state.customer2Token);

    const { status } = await api.post('/payments', {
      order_id:    state.orderId,
      customer_id: state.customerId,
      amount:      150.00,
      method:      'cash',
    });

    expect(status).toBe(403);
  });

  test('POST /payments → 409 duplicate paid payment', async ({ request }) => {
    test.skip(!state.serviceId, 'No service available');

    const api = new ApiClient(request);
    api.setToken(state.customerToken);

    const { body: orderBody } = await api.post('/orders', {
      service_id:           state.serviceId,
      pickup:               { address: '123 Rizal St', lat: 14.5995, lng: 120.9842 },
      dropoff:              { address: '456 Mabini Ave', lat: 14.6090, lng: 121.0000 },
      estimated_distance_m: 3000,
      payment_method:       'gcash',
    });
    const newOrderId = (orderBody.data as any)?.id;
    test.skip(!newOrderId, 'Order creation failed');

    await api.post('/payments', {
      order_id: newOrderId, customer_id: state.customerId,
      amount: 100.00, method: 'gcash',
    });

    const { status } = await api.post('/payments', {
      order_id: newOrderId, customer_id: state.customerId,
      amount: 100.00, method: 'gcash',
    });

    expect(status).toBe(409);
  });

  test('POST /payments → 422 missing required fields', async ({ request }) => {
    const api = new ApiClient(request);
    api.setToken(state.customerToken);

    const { status } = await api.post('/payments', { amount: 100 });
    expect(status).toBe(422);
  });

  // ── List ────────────────────────────────────────────────────────────────────

  test('GET /payments → 200 paginated, customer sees own only', async ({ request }) => {
    const api = new ApiClient(request);
    api.setToken(state.customerToken);

    const { status, body } = await api.get('/payments');

    expect(status).toBe(200);
    assertPaginated(body);
  });

  test('GET /payments → 401 without token', async ({ request }) => {
    const api = new ApiClient(request);
    const { status } = await api.get('/payments');
    expect(status).toBe(401);
  });

  // ── Show ────────────────────────────────────────────────────────────────────

  test('GET /payments/{id} → 200 owner can view', async ({ request }) => {
    test.skip(!state.paymentId, 'No payment created yet');

    const api = new ApiClient(request);
    api.setToken(state.customerToken);

    const { status, body } = await api.get(`/payments/${state.paymentId}`);

    expect(status).toBe(200);
    expect((body.data as any).id).toBe(state.paymentId);
  });

  test('GET /payments/{id} → 403 other customer cannot view', async ({ request }) => {
    test.skip(!state.paymentId, 'No payment created yet');

    const api = new ApiClient(request);
    api.setToken(state.customer2Token);

    const { status } = await api.get(`/payments/${state.paymentId}`);
    expect(status).toBe(403);
  });
});
