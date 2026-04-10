import { test, expect } from '@playwright/test';
import { ApiClient, makeEmail } from '../lib/api-client';

const state = {
  token:      '',
  articleId:  0,
  categoryId: 0,
};

test.describe.serial('FAQ & Help Center', () => {
  test('setup — register user', async ({ request }) => {
    const api = new ApiClient(request);
    const r = await api.register({
      name: 'FAQ User', email: makeEmail(),
      password: 'Password123!', password_confirmation: 'Password123!',
      role: 'customer',
    });
    state.token = r.token;
    expect(r.success).toBe(true);
  });

  test('GET /faq/categories → 200', async ({ request }) => {
    const api = new ApiClient(request);
    api.setToken(state.token);

    const { status, body } = await api.get('/faq/categories');
    expect(status).toBe(200);
    expect(body.success).toBe(true);

    const categories = (body.data ?? []) as any[];
    if (categories.length > 0) {
      state.categoryId = categories[0].id;
    }
  });

  test('GET /faq/categories → 401 without token', async ({ request }) => {
    const api = new ApiClient(request);
    const { status } = await api.get('/faq/categories');
    expect(status).toBe(401);
  });

  test('GET /faq/categories/{id} → 200 or 404', async ({ request }) => {
    test.skip(!state.categoryId, 'No categories available');

    const api = new ApiClient(request);
    api.setToken(state.token);

    const { status, body } = await api.get(`/faq/categories/${state.categoryId}`);
    expect([200, 404]).toContain(status);
    if (status === 200) {
      const articles = (body.data ?? []) as any[];
      if (articles.length > 0) state.articleId = articles[0].id;
    }
  });

  test('GET /faq/featured → 200', async ({ request }) => {
    const api = new ApiClient(request);
    api.setToken(state.token);

    const { status, body } = await api.get('/faq/featured');
    expect(status).toBe(200);
    expect(body.success).toBe(true);
  });

  test('GET /faq/popular → 200', async ({ request }) => {
    const api = new ApiClient(request);
    api.setToken(state.token);

    const { status, body } = await api.get('/faq/popular');
    expect(status).toBe(200);
    expect(body.success).toBe(true);
  });

  test('GET /faq/statistics → 200', async ({ request }) => {
    const api = new ApiClient(request);
    api.setToken(state.token);

    const { status, body } = await api.get('/faq/statistics');
    expect(status).toBe(200);
    expect(body.success).toBe(true);
  });

  test('GET /faq/search?q=delivery → 200', async ({ request }) => {
    const api = new ApiClient(request);
    api.setToken(state.token);

    const { status, body } = await api.get('/faq/search', { q: 'delivery' });
    expect(status).toBe(200);
    expect(body.success).toBe(true);
  });

  test('GET /faq/articles/{id} → 200 or 404', async ({ request }) => {
    test.skip(!state.articleId, 'No articles available');

    const api = new ApiClient(request);
    api.setToken(state.token);

    const { status } = await api.get(`/faq/articles/${state.articleId}`);
    expect([200, 404]).toContain(status);
  });

  test('POST /faq/articles/{id}/helpful → 200 or 404', async ({ request }) => {
    test.skip(!state.articleId, 'No articles available');

    const api = new ApiClient(request);
    api.setToken(state.token);

    const { status } = await api.post(`/faq/articles/${state.articleId}/helpful`);
    expect([200, 404]).toContain(status);
  });

  test('POST /faq/articles/{id}/not-helpful → 200 or 404', async ({ request }) => {
    test.skip(!state.articleId, 'No articles available');

    const api = new ApiClient(request);
    api.setToken(state.token);

    const { status } = await api.post(`/faq/articles/${state.articleId}/not-helpful`);
    expect([200, 404]).toContain(status);
  });
});
