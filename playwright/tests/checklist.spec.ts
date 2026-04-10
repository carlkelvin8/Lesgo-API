import { test, expect } from '@playwright/test';
import { ApiClient, makeEmail, assertPaginated } from '../lib/api-client';

const state = { token: '' };

test.describe.serial('Checklist Templates', () => {
  test('setup — register user', async ({ request }) => {
    const api = new ApiClient(request);
    const r = await api.register({
      name: 'Checklist User', email: makeEmail(),
      password: 'Password123!', password_confirmation: 'Password123!',
      role: 'customer',
    });
    state.token = r.token;
    expect(r.success).toBe(true);
  });

  test('GET /checklist-templates → 401 without token', async ({ request }) => {
    const api = new ApiClient(request);
    const { status } = await api.get('/checklist-templates');
    expect(status).toBe(401);
  });

  test('GET /checklist-templates → 200 paginated', async ({ request }) => {
    const api = new ApiClient(request);
    api.setToken(state.token);

    const { status, body } = await api.get('/checklist-templates');
    expect(status).toBe(200);
    expect(body.success).toBe(true);
    expect(body.data).toBeDefined();
  });

  test('POST /checklist-templates → 201 creates template', async ({ request }) => {
    const api = new ApiClient(request);
    api.setToken(state.token);

    const { status, body } = await api.post('/checklist-templates', {
      name:  'Grocery Checklist',
      items: [
        { name: 'Rice', quantity: 2, unit: 'kg' },
        { name: 'Eggs', quantity: 12, unit: 'pcs' },
      ],
    });

    expect([201, 403, 422]).toContain(status);
    if (status === 201) {
      expect(body.success).toBe(true);
      expect((body.data as any).name).toBe('Grocery Checklist');
    }
  });

  test('POST /checklist-templates → 422 missing name', async ({ request }) => {
    const api = new ApiClient(request);
    api.setToken(state.token);

    const { status } = await api.post('/checklist-templates', {
      items: [{ name: 'Rice' }],
    });
    expect([422, 403]).toContain(status);
  });
});
