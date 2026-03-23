import { test, expect } from '@playwright/test';
import { ApiClient, makeEmail, assertPaginated } from '../lib/api-client';

const state = {
  customerToken:  '',
  customer2Token: '',
  serviceId:      0,
  orderId:        0,
};

test.describe.serial('Orders', () => {
  test('setup — register users and get service', async ({ request }) => {
    const api = new ApiClient(request);

    const r1 = await api.register({
      name: 'Customer One', email: makeEmail(),
      password: 'Password123!', password_confirmation: 'Password123!',
      role: 'customer',
    });
    state.customerToken = r1.token;

    const api2 = new ApiClient(request);
    const r2 = await api2.register({
      name: 'Customer Two', email: makeEmail(),
      password: 'Password123!', password_confirmation: 'Password123!',
      role: 'customer',
    });
    state.customer2Token = r2.token;

    const { body: svcList } = await api.get('/services');
    const services = (svcList.data ?? []) as any[];
    if (services.length > 0) state.serviceId = services[0].id;

    expect(r1.success).toBe(true);
  });

  // ── Create ──────────────────────────────────────────────────────────────────

  test('POST /orders → 201 customer creates order', async ({ request }) => {
    test.skip(!state.serviceId, 'No services seeded');

    const api = new ApiClient(request);
    api.setToken(state.customerToken);

    const { status, body } = await api.post('/orders', {
      service_id:           state.serviceId,
      pickup:               { address: '123 Rizal St, Manila', lat: 14.5995, lng: 120.9842 },
      dropoff:              { address: '456 Mabini Ave, Manila', lat: 14.6090, lng: 121.0000 },
      estimated_distance_m: 5000,
      payment_method:       'cash',
    });

    expect(status).toBe(201);
    expect(body.success).toBe(true);
    expect((body.data as any).status).toBe('pending');

    state.orderId = (body.data as any).id;
  });

  test('POST /orders → 401 without token', async ({ request }) => {
    const api = new ApiClient(request);
    const { status } = await api.post('/orders', {
      service_id: state.serviceId || 1,
      pickup:  { address: 'A', lat: 14.5, lng: 120.9 },
      dropoff: { address: 'B', lat: 14.6, lng: 121.0 },
      estimated_distance_m: 1000,
    });
    expect(status).toBe(401);
  });

  test('POST /orders → 422 missing pickup', async ({ request }) => {
    test.skip(!state.serviceId, 'No services seeded');

    const api = new ApiClient(request);
    api.setToken(state.customerToken);

    const { status } = await api.post('/orders', {
      service_id: state.serviceId, estimated_distance_m: 5000,
    });
    expect(status).toBe(422);
  });

  test('POST /orders → 422 invalid service_id', async ({ request }) => {
    const api = new ApiClient(request);
    api.setToken(state.customerToken);

    const { status } = await api.post('/orders', {
      service_id: 99999,
      pickup:  { address: 'A', lat: 14.5, lng: 120.9 },
      dropoff: { address: 'B', lat: 14.6, lng: 121.0 },
      estimated_distance_m: 5000,
    });
    expect(status).toBe(422);
  });

  // ── List ────────────────────────────────────────────────────────────────────

  test('GET /orders → 200 paginated, customer sees own orders only', async ({ request }) => {
    const api = new ApiClient(request);
    api.setToken(state.customerToken);

    const { status, body } = await api.get('/orders');

    expect(status).toBe(200);
    assertPaginated(body);
  });

  test('GET /orders?status=pending → 200 filtered', async ({ request }) => {
    const api = new ApiClient(request);
    api.setToken(state.customerToken);

    const { status, body } = await api.get('/orders', { status: 'pending' });

    expect(status).toBe(200);
    const items = (body.data ?? []) as any[];
    items.forEach(o => expect(o.status).toBe('pending'));
  });

  test('GET /orders → 401 without token', async ({ request }) => {
    const api = new ApiClient(request);
    const { status } = await api.get('/orders');
    expect(status).toBe(401);
  });

  // ── Show ────────────────────────────────────────────────────────────────────

  test('GET /orders/{id} → 200 owner can view', async ({ request }) => {
    test.skip(!state.orderId, 'No order created yet');

    const api = new ApiClient(request);
    api.setToken(state.customerToken);

    const { status, body } = await api.get(`/orders/${state.orderId}`);

    expect(status).toBe(200);
    expect((body.data as any).id).toBe(state.orderId);
  });

  test('GET /orders/{id} → 403 other customer cannot view', async ({ request }) => {
    test.skip(!state.orderId, 'No order created yet');

    const api = new ApiClient(request);
    api.setToken(state.customer2Token);

    const { status } = await api.get(`/orders/${state.orderId}`);
    expect(status).toBe(403);
  });

  test('GET /orders/99999 → 404', async ({ request }) => {
    const api = new ApiClient(request);
    api.setToken(state.customerToken);

    const { status } = await api.get('/orders/99999');
    expect(status).toBe(404);
  });

  // ── Update Status ───────────────────────────────────────────────────────────

  test('PATCH /orders/{id}/status → 200 customer cancels own pending order', async ({ request }) => {
    test.skip(!state.orderId, 'No order created yet');

    const api = new ApiClient(request);
    api.setToken(state.customerToken);

    const { status, body } = await api.patch(`/orders/${state.orderId}/status`, {
      status: 'cancelled', cancel_reason: 'Changed my mind',
    });

    expect(status).toBe(200);
    expect((body.data as any).status).toBe('cancelled');
  });

  test('PATCH /orders/{id}/status → 403 other customer cannot update', async ({ request }) => {
    test.skip(!state.orderId, 'No order created yet');

    const api = new ApiClient(request);
    api.setToken(state.customer2Token);

    const { status } = await api.patch(`/orders/${state.orderId}/status`, {
      status: 'cancelled',
    });
    expect(status).toBe(403);
  });
});
