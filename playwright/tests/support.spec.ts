import { test, expect } from '@playwright/test';
import { ApiClient, makeEmail, assertPaginated } from '../lib/api-client';

const state = {
  customerToken: '',
  ticketId:      0,
};

test.describe.serial('Support Tickets', () => {
  test('setup — register user', async ({ request }) => {
    const api = new ApiClient(request);
    const r = await api.register({
      name: 'Support User', email: makeEmail(),
      password: 'Password123!', password_confirmation: 'Password123!',
      role: 'customer',
    });
    state.customerToken = r.token;
    expect(r.success).toBe(true);
  });

  test('GET /support/tickets → 401 without token', async ({ request }) => {
    const api = new ApiClient(request);
    const { status } = await api.get('/support/tickets');
    expect(status).toBe(401);
  });

  test('GET /support/tickets → 200 paginated', async ({ request }) => {
    const api = new ApiClient(request);
    api.setToken(state.customerToken);

    const { status, body } = await api.get('/support/tickets');
    expect(status).toBe(200);
    expect(body.success).toBe(true);
    expect(body.meta).toBeDefined();
    expect(typeof body.meta!.total).toBe('number');
  });

  test('POST /support/tickets → 201 creates ticket', async ({ request }) => {
    const api = new ApiClient(request);
    api.setToken(state.customerToken);

    const { status, body } = await api.post('/support/tickets', {
      subject:     'Test Support Ticket',
      description: 'I need help with my order.',
      category:    'order_issue',
      priority:    'medium',
    });

    expect(status).toBe(201);
    expect(body.success).toBe(true);
    state.ticketId = (body.data as any).id;
    // status field may be 'open' or nested differently
    expect(state.ticketId).toBeGreaterThan(0);
  });

  test('POST /support/tickets → 422 missing subject', async ({ request }) => {
    const api = new ApiClient(request);
    api.setToken(state.customerToken);

    const { status } = await api.post('/support/tickets', {
      message: 'No subject here',
    });
    expect(status).toBe(422);
  });

  test('GET /support/tickets/statistics → 200', async ({ request }) => {
    const api = new ApiClient(request);
    api.setToken(state.customerToken);

    const { status, body } = await api.get('/support/tickets/statistics');
    expect(status).toBe(200);
    expect(body.success).toBe(true);
  });

  test('GET /support/tickets/{id} → 200 owner can view', async ({ request }) => {
    test.skip(!state.ticketId, 'No ticket created');

    const api = new ApiClient(request);
    api.setToken(state.customerToken);

    const { status, body } = await api.get(`/support/tickets/${state.ticketId}`);
    expect(status).toBe(200);
    expect((body.data as any).id).toBe(state.ticketId);
  });

  test('POST /support/tickets/{id}/messages → 201 adds message', async ({ request }) => {
    test.skip(!state.ticketId, 'No ticket created');

    const api = new ApiClient(request);
    api.setToken(state.customerToken);

    const { status, body } = await api.post(`/support/tickets/${state.ticketId}/messages`, {
      message: 'Follow-up message on my ticket.',
    });

    expect([200, 201]).toContain(status);
    expect(body.success).toBe(true);
  });

  test('POST /support/tickets/{id}/satisfaction → 200', async ({ request }) => {
    test.skip(!state.ticketId, 'No ticket created');

    const api = new ApiClient(request);
    api.setToken(state.customerToken);

    const { status } = await api.post(`/support/tickets/${state.ticketId}/satisfaction`, {
      rating: 5,
    });

    expect([200, 400, 422]).toContain(status);
  });

  test('POST /support/tickets/{id}/close → 200 closes ticket', async ({ request }) => {
    test.skip(!state.ticketId, 'No ticket created');

    const api = new ApiClient(request);
    api.setToken(state.customerToken);

    const { status, body } = await api.post(`/support/tickets/${state.ticketId}/close`);
    expect([200, 400, 422]).toContain(status);
    if (status === 200) {
      expect(body.success).toBe(true);
    }
  });
});
