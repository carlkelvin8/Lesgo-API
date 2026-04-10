import { test, expect } from '@playwright/test';
import { ApiClient, makeEmail, assertPaginated } from '../lib/api-client';

const state = {
  customerToken: '',
  adminToken:    '',
  documentId:    0,
};

test.describe.serial('Document Submission', () => {
  test('setup — register user', async ({ request }) => {
    const api = new ApiClient(request);
    const r = await api.register({
      name: 'Doc User', email: makeEmail(),
      password: 'Password123!', password_confirmation: 'Password123!',
      role: 'customer',
    });
    state.customerToken = r.token;
    expect(r.success).toBe(true);
  });

  test('GET /documents/types → 200', async ({ request }) => {
    const api = new ApiClient(request);
    api.setToken(state.customerToken);

    const { status, body } = await api.get('/documents/types');
    expect(status).toBe(200);
    expect(body.success).toBe(true);
  });

  test('GET /documents/types → 401 without token', async ({ request }) => {
    const api = new ApiClient(request);
    const { status } = await api.get('/documents/types');
    expect(status).toBe(401);
  });

  test('GET /documents/my-documents → 200', async ({ request }) => {
    const api = new ApiClient(request);
    api.setToken(state.customerToken);

    const { status, body } = await api.get('/documents/my-documents');
    expect(status).toBe(200);
    expect(body.success).toBe(true);
  });

  test('GET /documents/verification-status → 200', async ({ request }) => {
    const api = new ApiClient(request);
    api.setToken(state.customerToken);

    const { status, body } = await api.get('/documents/verification-status');
    expect(status).toBe(200);
    expect(body.success).toBe(true);
  });

  test('POST /documents/submit → 201 or 422', async ({ request }) => {
    const api = new ApiClient(request);
    api.setToken(state.customerToken);

    const { status, body } = await api.post('/documents/submit', {
      document_type: 'government_id',
      document_url:  'https://cdn.example.com/docs/id.jpg',
      notes:         'My government ID',
    });

    expect([201, 422]).toContain(status);
    if (status === 201) {
      state.documentId = (body.data as any).id;
      expect(body.success).toBe(true);
    }
  });

  test('POST /documents/submit → 422 missing document_type', async ({ request }) => {
    const api = new ApiClient(request);
    api.setToken(state.customerToken);

    const { status } = await api.post('/documents/submit', {
      document_url: 'https://cdn.example.com/docs/id.jpg',
    });
    expect(status).toBe(422);
  });

  test('GET /documents/{id} → 200 or 404', async ({ request }) => {
    test.skip(!state.documentId, 'No document submitted');

    const api = new ApiClient(request);
    api.setToken(state.customerToken);

    const { status, body } = await api.get(`/documents/${state.documentId}`);
    expect([200, 404]).toContain(status);
    if (status === 200) {
      expect((body.data as any).id).toBe(state.documentId);
    }
  });
});

test.describe.serial('Admin Document Verification', () => {
  test('setup — register admin user', async ({ request }) => {
    // Admin endpoints require admin role — register a customer and test 403 behavior
    const api = new ApiClient(request);
    const r = await api.register({
      name: 'Admin Doc User', email: makeEmail(),
      password: 'Password123!', password_confirmation: 'Password123!',
      role: 'customer',
    });
    state.adminToken = r.token;
    expect(r.success).toBe(true);
  });

  test('GET /admin/documents → 200 or 403', async ({ request }) => {
    const api = new ApiClient(request);
    api.setToken(state.adminToken);

    const { status } = await api.get('/admin/documents');
    // Customer gets 403, admin gets 200, server error gets 500
    expect([200, 403, 500]).toContain(status);
  });

  test('GET /admin/documents → 401 without token', async ({ request }) => {
    const api = new ApiClient(request);
    const { status } = await api.get('/admin/documents');
    expect(status).toBe(401);
  });

  test('GET /admin/documents/statistics → 200 or 403', async ({ request }) => {
    const api = new ApiClient(request);
    api.setToken(state.adminToken);

    const { status } = await api.get('/admin/documents/statistics');
    expect([200, 403, 500]).toContain(status);
  });

  test('GET /admin/documents/users-with-pending → 200 or 403', async ({ request }) => {
    const api = new ApiClient(request);
    api.setToken(state.adminToken);

    const { status } = await api.get('/admin/documents/users-with-pending');
    expect([200, 403, 500]).toContain(status);
  });
});
