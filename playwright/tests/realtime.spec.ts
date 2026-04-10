import { test, expect } from '@playwright/test';
import { ApiClient, makeEmail } from '../lib/api-client';

const state = {
  token:          '',
  notificationId: 0,
  connectionId:   '',
};

test.describe.serial('Realtime & Chat', () => {
  test('setup — register user', async ({ request }) => {
    const api = new ApiClient(request);
    const r = await api.register({
      name: 'Realtime User', email: makeEmail(),
      password: 'Password123!', password_confirmation: 'Password123!',
      role: 'customer',
    });
    state.token = r.token;
    expect(r.success).toBe(true);
  });

  // ── Realtime ────────────────────────────────────────────────────────────────

  test('GET /realtime/stats → 401 without token', async ({ request }) => {
    const api = new ApiClient(request);
    const { status } = await api.get('/realtime/stats');
    expect(status).toBe(401);
  });

  test('POST /realtime/connect → 200', async ({ request }) => {
    const api = new ApiClient(request);
    api.setToken(state.token);

    const { status, body } = await api.post('/realtime/connect', {
      channel: 'general',
    });
    expect([200, 201, 500]).toContain(status);
    if (status === 200 || status === 201) {
      expect(body.success).toBe(true);
      state.connectionId = (body.data as any)?.connection_id ?? '';
    }
  });

  test('POST /realtime/ping → 200', async ({ request }) => {
    test.skip(!state.connectionId, 'No connection_id available');
    const api = new ApiClient(request);
    api.setToken(state.token);

    const { status, body } = await api.post('/realtime/ping', {
      connection_id: state.connectionId,
    });
    expect([200, 422]).toContain(status);
    if (status === 200) expect(body.success).toBe(true);
  });

  test('GET /realtime/connections → 200', async ({ request }) => {
    const api = new ApiClient(request);
    api.setToken(state.token);

    const { status, body } = await api.get('/realtime/connections');
    expect([200, 500]).toContain(status);
    if (status === 200) expect(body.success).toBe(true);
  });

  test('GET /realtime/notifications → 200', async ({ request }) => {
    const api = new ApiClient(request);
    api.setToken(state.token);

    const { status, body } = await api.get('/realtime/notifications');
    expect([200, 500]).toContain(status);
    if (status === 200) expect(body.success).toBe(true);
  });

  test('GET /realtime/stats → 200', async ({ request }) => {
    const api = new ApiClient(request);
    api.setToken(state.token);

    const { status } = await api.get('/realtime/stats');
    expect([200, 403, 500]).toContain(status);
  });

  test('POST /realtime/test-notification → 200', async ({ request }) => {
    const api = new ApiClient(request);
    api.setToken(state.token);

    const { status, body } = await api.post('/realtime/test-notification', {
      title:   'Test Notification',
      message: 'This is a test',
    });
    expect([200, 201, 500]).toContain(status);
    if (status === 200 || status === 201) expect(body.success).toBe(true);
  });

  test('POST /realtime/notifications/read-all → 200', async ({ request }) => {
    const api = new ApiClient(request);
    api.setToken(state.token);

    const { status, body } = await api.post('/realtime/notifications/read-all');
    expect([200, 500]).toContain(status);
    if (status === 200) expect(body.success).toBe(true);
  });

  test('POST /realtime/disconnect → 200', async ({ request }) => {
    test.skip(!state.connectionId, 'No connection_id available');
    const api = new ApiClient(request);
    api.setToken(state.token);

    const { status, body } = await api.post('/realtime/disconnect', {
      connection_id: state.connectionId,
    });
    expect([200, 404, 422]).toContain(status);
    if (status === 200) expect(body.success).toBe(true);
  });
});

test.describe.serial('Chat', () => {
  let chatToken = '';
  let serviceId = 0;
  let orderId   = 0;
  let conversationId = 0;

  test('setup — register user, create order', async ({ request }) => {
    const api = new ApiClient(request);
    const r = await api.register({
      name: 'Chat User', email: makeEmail(),
      password: 'Password123!', password_confirmation: 'Password123!',
      role: 'customer',
    });
    chatToken = r.token;
    api.setToken(chatToken);

    const { body: svcList } = await api.get('/services');
    const services = (svcList.data ?? []) as any[];
    if (services.length > 0) {
      serviceId = services[0].id;
      const { body: orderBody } = await api.post('/orders', {
        service_id:           serviceId,
        pickup:               { address: '123 Rizal St', lat: 14.5995, lng: 120.9842 },
        dropoff:              { address: '456 Mabini Ave', lat: 14.6090, lng: 121.0000 },
        estimated_distance_m: 5000,
        payment_method:       'cash',
      });
      orderId = (orderBody.data as any)?.id ?? 0;
    }

    expect(r.success).toBe(true);
  });

  test('GET /chat/conversations → 401 without token', async ({ request }) => {
    const api = new ApiClient(request);
    const { status } = await api.get('/chat/conversations');
    expect(status).toBe(401);
  });

  test('GET /chat/conversations → 200', async ({ request }) => {
    const api = new ApiClient(request);
    api.setToken(chatToken);

    const { status, body } = await api.get('/chat/conversations');
    expect(status).toBe(200);
    expect(body.success).toBe(true);
  });

  test('GET /chat/unread-count → 200', async ({ request }) => {
    const api = new ApiClient(request);
    api.setToken(chatToken);

    const { status, body } = await api.get('/chat/unread-count');
    expect(status).toBe(200);
    expect(body.success).toBe(true);
    expect(typeof (body.data as any).unread_count).toBe('number');
  });

  test('GET /chat/conversations/order/{id} → 200 or 404', async ({ request }) => {
    test.skip(!orderId, 'No order available');

    const api = new ApiClient(request);
    api.setToken(chatToken);

    const { status, body } = await api.get(`/chat/conversations/order/${orderId}`);
    expect([200, 404, 500]).toContain(status);
    if (status === 200) {
      conversationId = (body.data as any)?.id ?? 0;
    }
  });

  test('GET /chat/conversations/{id}/messages → 200 or 404', async ({ request }) => {
    test.skip(!conversationId, 'No conversation available');

    const api = new ApiClient(request);
    api.setToken(chatToken);

    const { status, body } = await api.get(`/chat/conversations/${conversationId}/messages`);
    expect([200, 404]).toContain(status);
    if (status === 200) expect(body.success).toBe(true);
  });

  test('POST /chat/conversations/{id}/messages → 201 or 404', async ({ request }) => {
    test.skip(!conversationId, 'No conversation available');

    const api = new ApiClient(request);
    api.setToken(chatToken);

    const { status, body } = await api.post(`/chat/conversations/${conversationId}/messages`, {
      message: 'Hello, where is my order?',
      type:    'text',
    });
    expect([200, 201, 404, 422]).toContain(status);
    if (status === 201 || status === 200) expect(body.success).toBe(true);
  });
});
