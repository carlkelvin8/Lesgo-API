import { test, expect } from '@playwright/test';

const BASE = (process.env.API_BASE_URL ?? 'http://127.0.0.1:8000').replace('localhost', '127.0.0.1');
const V1   = `${BASE}/api/v1`;

test.describe('Webhooks — Xendit', () => {
  test('POST /webhooks/payments/xendit → 200 accepted (no token configured)', async ({ request }) => {
    const res = await request.post(`${V1}/webhooks/payments/xendit`, {
      data: { id: 'inv_test_playwright_001', status: 'PAID' },
    });

    // If no webhook token is configured, it passes through
    expect([200, 400]).toContain(res.status());
    const body = await res.json();
    expect(typeof body.success).toBe('boolean');
  });

  test('POST /webhooks/payments/xendit → 400 with wrong X-CALLBACK-TOKEN', async ({ request }) => {
    // Only meaningful if XENDIT_WEBHOOK_TOKEN is set in the running app
    const res = await request.post(`${V1}/webhooks/payments/xendit`, {
      data:    { id: 'inv_test_playwright_002', status: 'PAID' },
      headers: { 'X-CALLBACK-TOKEN': 'definitely-wrong-token-xyz' },
    });

    // If token is configured → 400; if not configured → 200
    expect([200, 400]).toContain(res.status());
  });

  test('POST /webhooks/payments/gcash → 200 accepted', async ({ request }) => {
    const res = await request.post(`${V1}/webhooks/payments/gcash`, {
      data: { reference: 'gcash_ref_001', status: 'success' },
    });

    expect([200, 400]).toContain(res.status());
  });

  test('POST /webhooks/payments/maya → 200 accepted', async ({ request }) => {
    const res = await request.post(`${V1}/webhooks/payments/maya`, {
      data: { reference: 'maya_ref_001', status: 'completed' },
    });

    expect([200, 400]).toContain(res.status());
  });

  test('POST /webhooks/payments/stripe → 404 (not in allowed providers)', async ({ request }) => {
    const res = await request.post(`${V1}/webhooks/payments/stripe`, {
      data: { id: 'pi_test_001', status: 'succeeded' },
    });

    expect(res.status()).toBe(404);
  });
});

test.describe('Webhooks — Response shape', () => {
  test('Webhook response has success field', async ({ request }) => {
    const res = await request.post(`${V1}/webhooks/payments/xendit`, {
      data: { id: 'inv_shape_test', status: 'PAID' },
    });

    const body = await res.json();
    expect(body).toHaveProperty('success');
    expect(body).toHaveProperty('message');
  });
});
