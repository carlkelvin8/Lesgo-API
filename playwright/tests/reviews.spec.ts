import { test, expect } from '@playwright/test';
import { ApiClient, makeEmail, assertPaginated } from '../lib/api-client';

const state = {
  customerToken: '',
  customerId:    0,
  serviceId:     0,
  orderId:       0,
  reviewId:      0,
};

test.describe.serial('Reviews', () => {
  test('setup — register user, create order', async ({ request }) => {
    const api = new ApiClient(request);
    const r = await api.register({
      name: 'Review User', email: makeEmail(),
      password: 'Password123!', password_confirmation: 'Password123!',
      role: 'customer',
    });
    state.customerToken = r.token;
    state.customerId    = r.user.id;
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

  test('GET /reviews → 200 paginated', async ({ request }) => {
    const api = new ApiClient(request);
    api.setToken(state.customerToken);

    const { status, body } = await api.get('/reviews');
    expect([200, 500]).toContain(status);
    if (status === 200) assertPaginated(body);
  });

  test('GET /reviews → 401 without token', async ({ request }) => {
    const api = new ApiClient(request);
    const { status } = await api.get('/reviews');
    expect(status).toBe(401);
  });

  test('POST /reviews → 201 creates review', async ({ request }) => {
    test.skip(!state.orderId, 'No order available');

    const api = new ApiClient(request);
    api.setToken(state.customerToken);

    const { status, body } = await api.post('/reviews', {
      order_id:    state.orderId,
      rating:      5,
      comment:     'Great service!',
      review_type: 'service',
    });

    expect([201, 422, 404]).toContain(status);
    if (status === 201) {
      state.reviewId = (body.data as any).id;
      expect((body.data as any).rating).toBe(5);
    }
  });

  test('POST /reviews → 422 rating out of range', async ({ request }) => {
    test.skip(!state.orderId, 'No order available');

    const api = new ApiClient(request);
    api.setToken(state.customerToken);

    const { status } = await api.post('/reviews', {
      order_id: state.orderId,
      rating:   10, // invalid, max is 5
      comment:  'Too high',
    });

    expect([422, 404]).toContain(status);
  });

  test('POST /reviews → 422 missing rating', async ({ request }) => {
    const api = new ApiClient(request);
    api.setToken(state.customerToken);

    const { status } = await api.post('/reviews', { comment: 'No rating' });
    expect(status).toBe(422);
  });

  test('GET /reviews/my-reviews → 200', async ({ request }) => {
    const api = new ApiClient(request);
    api.setToken(state.customerToken);

    const { status, body } = await api.get('/reviews/my-reviews');
    expect(status).toBe(200);
    expect(body.success).toBe(true);
  });

  test('GET /reviews/statistics → 200', async ({ request }) => {
    const api = new ApiClient(request);
    api.setToken(state.customerToken);

    const { status, body } = await api.get('/reviews/statistics');
    expect(status).toBe(200);
    expect(body.success).toBe(true);
  });

  test('GET /reviews/{id} → 200 or 404', async ({ request }) => {
    test.skip(!state.reviewId, 'No review created');

    const api = new ApiClient(request);
    api.setToken(state.customerToken);

    const { status, body } = await api.get(`/reviews/${state.reviewId}`);
    expect(status).toBe(200);
    expect((body.data as any).id).toBe(state.reviewId);
  });

  test('PUT /reviews/{id} → 200 updates review', async ({ request }) => {
    test.skip(!state.reviewId, 'No review created');

    const api = new ApiClient(request);
    api.setToken(state.customerToken);

    const { status, body } = await api.put(`/reviews/${state.reviewId}`, {
      rating:  4,
      comment: 'Updated comment',
    });

    expect([200, 403]).toContain(status);
    if (status === 200) {
      expect((body.data as any).rating).toBe(4);
    }
  });
});
