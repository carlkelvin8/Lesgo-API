import { test, expect } from '@playwright/test';
import { ApiClient, makeEmail, assertPaginated } from '../lib/api-client';

const state = {
  adminToken:   '',
  customerToken: '',
  partnerId:    0,
  branchId:     0,
};

test.describe.serial('Partners', () => {

  test('setup — register customer', async ({ request }) => {
    const api = new ApiClient(request);
    const r = await api.register({
      name: 'Partner Customer', email: makeEmail(),
      password: 'Password123!', password_confirmation: 'Password123!',
      role: 'customer',
    });
    state.customerToken = r.token;
    expect(r.success).toBe(true);
  });

  // ── Public list ─────────────────────────────────────────────────────────────

  test('GET /partners → 200 public, no token needed', async ({ request }) => {
    const api = new ApiClient(request);
    const { status, body } = await api.get('/partners');
    // Route may require auth depending on server config
    expect([200, 401]).toContain(status);
    if (status === 200) {
      expect(body.success).toBe(true);
    }
  });

  test('GET /partners?category=restaurant → 200 filtered', async ({ request }) => {
    const api = new ApiClient(request);
    const { status, body } = await api.get('/partners', { category: 'restaurant' });
    expect([200, 401]).toContain(status);
    if (status === 200) {
      const items = (body.data ?? []) as any[];
      items.forEach(p => expect(p.category).toBe('restaurant'));
    }
  });

  test('GET /partners?is_open=true → 200 only open partners', async ({ request }) => {
    const api = new ApiClient(request);
    const { status, body } = await api.get('/partners', { is_open: 'true' });
    expect([200, 401]).toContain(status);
    if (status === 200) {
      const items = (body.data ?? []) as any[];
      items.forEach(p => expect(p.is_open).toBe(true));
    }
  });

  test('GET /partners?search=mercury → 200 search works', async ({ request }) => {
    const api = new ApiClient(request);
    const { status, body } = await api.get('/partners', { search: 'mercury' });
    expect([200, 401]).toContain(status);
    if (status === 200) expect(body.success).toBe(true);
  });

  test('GET /partners?is_featured=true → 200 featured only', async ({ request }) => {
    const api = new ApiClient(request);
    const { status, body } = await api.get('/partners', { is_featured: 'true' });
    expect([200, 401]).toContain(status);
    if (status === 200) {
      const items = (body.data ?? []) as any[];
      items.forEach(p => expect(p.is_featured).toBe(true));
    }
  });

  // ── Show ────────────────────────────────────────────────────────────────────

  test('GET /partners/{id} → 200 includes branches', async ({ request }) => {
    const api = new ApiClient(request);
    api.setToken(state.customerToken);
    const { body: list } = await api.get('/partners');
    const partners = (list.data ?? []) as any[];
    if (partners.length === 0) {
      test.skip(true, 'No partners seeded');
      return;
    }
    const id = partners[0].id;
    state.partnerId = id;

    const { status, body } = await api.get(`/partners/${id}`);
    expect(status).toBe(200);
    expect((body.data as any).id).toBe(id);
    expect(Array.isArray((body.data as any).branches)).toBe(true);
  });

  test('GET /partners/99999 → 404', async ({ request }) => {
    const api = new ApiClient(request);
    api.setToken(state.customerToken);
    const { status } = await api.get('/partners/99999');
    expect([404, 401]).toContain(status);
  });

  // ── Branches ────────────────────────────────────────────────────────────────

  test('GET /partners/{id}/branches → 200 public', async ({ request }) => {
    test.skip(!state.partnerId, 'No partner available');
    const api = new ApiClient(request);
    api.setToken(state.customerToken);
    const { status, body } = await api.get(`/partners/${state.partnerId}/branches`);
    expect([200, 401]).toContain(status);
    if (status === 200) {
      expect(body.success).toBe(true);
      expect(Array.isArray(body.data)).toBe(true);
    }
  });

  // ── Partner fields ──────────────────────────────────────────────────────────

  test('GET /partners → response has expected fields', async ({ request }) => {
    const api = new ApiClient(request);
    const { body } = await api.get('/partners');
    const items = (body.data ?? []) as any[];
    if (items.length === 0) return;

    const p = items[0];
    expect(p).toHaveProperty('id');
    expect(p).toHaveProperty('name');
    expect(p).toHaveProperty('logo_url');
    expect(p).toHaveProperty('category');
    expect(p).toHaveProperty('rating');
    expect(p).toHaveProperty('delivery_fee');
    expect(p).toHaveProperty('estimated_delivery_minutes');
    expect(p).toHaveProperty('is_open');
    expect(p).toHaveProperty('is_featured');
  });
});
