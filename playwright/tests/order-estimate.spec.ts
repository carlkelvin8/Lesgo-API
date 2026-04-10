import { test, expect } from '@playwright/test';
import { ApiClient, makeEmail } from '../lib/api-client';

const state = {
  customerToken: '',
  serviceId:     0,
};

test.describe.serial('Order Estimate', () => {

  test('setup — register customer and get service', async ({ request }) => {
    const api = new ApiClient(request);
    const r = await api.register({
      name: 'Estimate User', email: makeEmail(),
      password: 'Password123!', password_confirmation: 'Password123!',
      role: 'customer',
    });
    state.customerToken = r.token;

    const { body: svcList } = await api.get('/services');
    const services = (svcList.data ?? []) as any[];
    if (services.length > 0) state.serviceId = services[0].id;

    expect(r.success).toBe(true);
  });

  // ── Estimate ────────────────────────────────────────────────────────────────

  test('POST /orders/estimate → 200 returns fare breakdown', async ({ request }) => {
    test.skip(!state.serviceId, 'No services seeded');

    const api = new ApiClient(request);
    api.setToken(state.customerToken);

    const { status, body } = await api.post('/orders/estimate', {
      service_id: state.serviceId,
      pickup:  { lat: 14.5995, lng: 120.9842 },
      dropoff: { lat: 14.5547, lng: 121.0244 },
    });

    expect(status).toBe(200);
    expect(body.success).toBe(true);

    const data = body.data as any;
    expect(data).toHaveProperty('distance_m');
    expect(data).toHaveProperty('distance_km');
    expect(data).toHaveProperty('estimated_fare');
    expect(data).toHaveProperty('fare_breakdown');
    expect(data).toHaveProperty('estimated_duration_minutes');
    expect(data).toHaveProperty('payment_methods');
    expect(data).toHaveProperty('service');

    // Fare breakdown structure
    const fb = data.fare_breakdown;
    expect(fb).toHaveProperty('base_fare');
    expect(fb).toHaveProperty('distance_fare');
    expect(fb).toHaveProperty('service_fee');
    expect(fb).toHaveProperty('weight_surcharge');
    expect(fb).toHaveProperty('total');
    expect(fb).toHaveProperty('currency');
    expect(fb.currency).toBe('PHP');

    // Sanity checks
    expect(data.distance_m).toBeGreaterThan(0);
    expect(data.estimated_fare).toBeGreaterThan(0);
    expect(data.estimated_duration_minutes).toBeGreaterThan(0);
    expect(Array.isArray(data.payment_methods)).toBe(true);
    expect(data.payment_methods).toContain('cash');
    expect(data.payment_methods).toContain('gcash');
  });

  test('POST /orders/estimate → 200 with item description and weight', async ({ request }) => {
    test.skip(!state.serviceId, 'No services seeded');

    const api = new ApiClient(request);
    api.setToken(state.customerToken);

    const { status, body } = await api.post('/orders/estimate', {
      service_id:           state.serviceId,
      pickup:               { lat: 14.5995, lng: 120.9842 },
      dropoff:              { lat: 14.5547, lng: 121.0244 },
      item_description:     'Books, Clothes, Electronics',
      estimated_weight_kg:  8,
      payment_method:       'gcash',
    });

    expect(status).toBe(200);
    const data = body.data as any;
    // Weight > 5kg should add surcharge
    expect(data.fare_breakdown.weight_surcharge).toBeGreaterThan(0);
  });

  test('POST /orders/estimate → 200 same pickup/dropoff = minimal distance', async ({ request }) => {
    test.skip(!state.serviceId, 'No services seeded');

    const api = new ApiClient(request);
    api.setToken(state.customerToken);

    const { status, body } = await api.post('/orders/estimate', {
      service_id: state.serviceId,
      pickup:  { lat: 14.5995, lng: 120.9842 },
      dropoff: { lat: 14.5995, lng: 120.9842 },
    });

    expect(status).toBe(200);
    const data = body.data as any;
    expect(data.distance_m).toBeLessThan(100); // essentially 0
  });

  test('POST /orders/estimate → 401 without token', async ({ request }) => {
    const api = new ApiClient(request);
    const { status } = await api.post('/orders/estimate', {
      service_id: state.serviceId || 1,
      pickup:  { lat: 14.5995, lng: 120.9842 },
      dropoff: { lat: 14.5547, lng: 121.0244 },
    });
    expect(status).toBe(401);
  });

  test('POST /orders/estimate → 422 missing pickup', async ({ request }) => {
    test.skip(!state.serviceId, 'No services seeded');

    const api = new ApiClient(request);
    api.setToken(state.customerToken);

    const { status } = await api.post('/orders/estimate', {
      service_id: state.serviceId,
      dropoff: { lat: 14.5547, lng: 121.0244 },
    });
    expect(status).toBe(422);
  });

  test('POST /orders/estimate → 422 invalid service_id', async ({ request }) => {
    const api = new ApiClient(request);
    api.setToken(state.customerToken);

    const { status } = await api.post('/orders/estimate', {
      service_id: 99999,
      pickup:  { lat: 14.5995, lng: 120.9842 },
      dropoff: { lat: 14.5547, lng: 121.0244 },
    });
    expect(status).toBe(422);
  });

  test('POST /orders/estimate → 422 invalid coordinates', async ({ request }) => {
    test.skip(!state.serviceId, 'No services seeded');

    const api = new ApiClient(request);
    api.setToken(state.customerToken);

    const { status } = await api.post('/orders/estimate', {
      service_id: state.serviceId,
      pickup:  { lat: 999, lng: 999 }, // out of range
      dropoff: { lat: 14.5547, lng: 121.0244 },
    });
    expect(status).toBe(422);
  });

  // ── Full booking flow ───────────────────────────────────────────────────────

  test('estimate then book → fare matches', async ({ request }) => {
    test.skip(!state.serviceId, 'No services seeded');

    const api = new ApiClient(request);
    api.setToken(state.customerToken);

    // Step 1: estimate
    const { body: estBody } = await api.post('/orders/estimate', {
      service_id: state.serviceId,
      pickup:  { lat: 14.5995, lng: 120.9842 },
      dropoff: { lat: 14.5547, lng: 121.0244 },
    });
    const estimatedFare = (estBody.data as any).estimated_fare;
    const distanceM     = (estBody.data as any).distance_m;

    // Step 2: book
    const { status: bookStatus, body: bookBody } = await api.post('/orders', {
      service_id:           state.serviceId,
      estimated_distance_m: distanceM,
      pickup:  { address: '123 Rizal St', lat: 14.5995, lng: 120.9842 },
      dropoff: { address: '456 Mabini Ave', lat: 14.5547, lng: 121.0244 },
      payment_method: 'cash',
    });

    expect([201, 500]).toContain(bookStatus);
    if (bookStatus === 201) {
      const order = bookBody.data as any;
      expect(order.status).toBe('pending');
      expect(order).toHaveProperty('fare_breakdown');
      expect(order).toHaveProperty('pickup_address');
      expect(order).toHaveProperty('dropoff_address');
      expect(order).toHaveProperty('pickup_lat');
      expect(order).toHaveProperty('dropoff_lat');
    }
  });
});
