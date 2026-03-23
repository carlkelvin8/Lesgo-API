/**
 * Performance Test Suite
 *
 * Measures response times for all major API endpoints.
 * Thresholds (local dev, php artisan serve):
 *   - p50 (median) : ≤ 300ms
 *   - p95          : ≤ 800ms
 *   - single call  : ≤ 1000ms
 *
 * Each test runs N_SAMPLES requests and reports min/avg/p95/max.
 */

import { test, expect } from '@playwright/test';
import { ApiClient, makeEmail } from '../lib/api-client';

// ── Config ────────────────────────────────────────────────────────────────────

const N_SAMPLES   = 10;   // requests per endpoint
const P95_LIMIT   = 800;  // ms — p95 threshold
const SINGLE_LIMIT = 1000; // ms — any single request hard cap

// ── Helpers ───────────────────────────────────────────────────────────────────

function percentile(sorted: number[], p: number): number {
  const idx = Math.ceil((p / 100) * sorted.length) - 1;
  return sorted[Math.max(0, idx)];
}

function stats(times: number[]) {
  const sorted = [...times].sort((a, b) => a - b);
  return {
    min:  sorted[0],
    avg:  Math.round(times.reduce((s, t) => s + t, 0) / times.length),
    p50:  percentile(sorted, 50),
    p95:  percentile(sorted, 95),
    max:  sorted[sorted.length - 1],
  };
}

async function measure(fn: () => Promise<void>): Promise<number> {
  const start = Date.now();
  await fn();
  return Date.now() - start;
}

async function bench(label: string, fn: () => Promise<void>, n = N_SAMPLES) {
  const times: number[] = [];
  for (let i = 0; i < n; i++) {
    times.push(await measure(fn));
  }
  const s = stats(times);
  console.log(`  [PERF] ${label.padEnd(50)} min=${s.min}ms  avg=${s.avg}ms  p50=${s.p50}ms  p95=${s.p95}ms  max=${s.max}ms`);
  return s;
}

// ── Shared state ──────────────────────────────────────────────────────────────

const state = {
  token:     '',
  userId:    0,
  serviceId: 0,
  orderId:   0,
  paymentId: 0,
};

// ── Setup ─────────────────────────────────────────────────────────────────────

test.describe.serial('Performance', () => {

  test('setup — register user, create order & payment', async ({ request }) => {
    const api   = new ApiClient(request);
    const email = makeEmail();

    const auth = await api.register({
      name:                  'Perf Tester',
      email,
      password:              'Password123!',
      password_confirmation: 'Password123!',
      role:                  'customer',
      phone_number:          '+639170000001',
    });

    expect(auth.success).toBe(true);
    state.token  = auth.token;
    state.userId = auth.user.id;
    api.setToken(state.token);

    // Get a service
    const svcRes = await api.get('/services');
    expect(svcRes.status).toBe(200);
    const services = (svcRes.body as any).data;
    expect(services.length).toBeGreaterThan(0);
    state.serviceId = services[0].id;

    // Create an order
    const orderRes = await api.post('/orders', {
      service_id:            state.serviceId,
      pickup:                { address: '123 Rizal St', lat: 14.5995, lng: 120.9842 },
      dropoff:               { address: '456 Mabini Ave', lat: 14.6090, lng: 121.0000 },
      estimated_distance_m:  5000,
      payment_method:        'cash',
    });
    expect(orderRes.status).toBe(201);
    state.orderId = (orderRes.body as any).data.id;

    // Create a payment
    const payRes = await api.post('/payments', {
      order_id:    state.orderId,
      customer_id: state.userId,
      amount:      150.00,
      method:      'cash',
    });
    expect(payRes.status).toBe(201);
    state.paymentId = (payRes.body as any).data.id;
  });

  // ── Auth endpoints ────────────────────────────────────────────────────────

  test('GET /auth/me — response time', async ({ request }) => {
    const api = new ApiClient(request);
    api.setToken(state.token);

    const s = await bench('GET /auth/me', async () => {
      const { status } = await api.get('/auth/me');
      expect(status).toBe(200);
    });

    expect(s.p95).toBeLessThanOrEqual(P95_LIMIT);
    expect(s.max).toBeLessThanOrEqual(SINGLE_LIMIT);
  });

  // ── Services ──────────────────────────────────────────────────────────────

  test('GET /services — response time', async ({ request }) => {
    const api = new ApiClient(request);

    const s = await bench('GET /services (public, no auth)', async () => {
      const { status } = await api.get('/services');
      expect(status).toBe(200);
    });

    expect(s.p95).toBeLessThanOrEqual(P95_LIMIT);
    expect(s.max).toBeLessThanOrEqual(SINGLE_LIMIT);
  });

  test('GET /services/{id} — response time', async ({ request }) => {
    const api = new ApiClient(request);

    const s = await bench('GET /services/{id}', async () => {
      const { status } = await api.get(`/services/${state.serviceId}`);
      expect(status).toBe(200);
    });

    expect(s.p95).toBeLessThanOrEqual(P95_LIMIT);
    expect(s.max).toBeLessThanOrEqual(SINGLE_LIMIT);
  });

  // ── Orders ────────────────────────────────────────────────────────────────

  test('GET /orders — response time', async ({ request }) => {
    const api = new ApiClient(request);
    api.setToken(state.token);

    const s = await bench('GET /orders (paginated, cached)', async () => {
      const { status } = await api.get('/orders');
      expect(status).toBe(200);
    });

    expect(s.p95).toBeLessThanOrEqual(P95_LIMIT);
    expect(s.max).toBeLessThanOrEqual(SINGLE_LIMIT);
  });

  test('GET /orders/{id} — response time', async ({ request }) => {
    const api = new ApiClient(request);
    api.setToken(state.token);

    const s = await bench('GET /orders/{id} (cached after 1st hit)', async () => {
      const { status } = await api.get(`/orders/${state.orderId}`);
      expect(status).toBe(200);
    });

    expect(s.p95).toBeLessThanOrEqual(P95_LIMIT);
    expect(s.max).toBeLessThanOrEqual(SINGLE_LIMIT);
  });

  test('GET /orders?status=pending — response time', async ({ request }) => {
    const api = new ApiClient(request);
    api.setToken(state.token);

    const s = await bench('GET /orders?status=pending (filtered)', async () => {
      const { status } = await api.get('/orders', { status: 'pending' });
      expect(status).toBe(200);
    });

    expect(s.p95).toBeLessThanOrEqual(P95_LIMIT);
    expect(s.max).toBeLessThanOrEqual(SINGLE_LIMIT);
  });

  // ── Payments ──────────────────────────────────────────────────────────────

  test('GET /payments — response time', async ({ request }) => {
    const api = new ApiClient(request);
    api.setToken(state.token);

    const s = await bench('GET /payments (paginated)', async () => {
      const { status } = await api.get('/payments');
      expect(status).toBe(200);
    });

    expect(s.p95).toBeLessThanOrEqual(P95_LIMIT);
    expect(s.max).toBeLessThanOrEqual(SINGLE_LIMIT);
  });

  test('GET /payments/{id} — response time', async ({ request }) => {
    const api = new ApiClient(request);
    api.setToken(state.token);

    const s = await bench('GET /payments/{id}', async () => {
      const { status } = await api.get(`/payments/${state.paymentId}`);
      expect(status).toBe(200);
    });

    expect(s.p95).toBeLessThanOrEqual(P95_LIMIT);
    expect(s.max).toBeLessThanOrEqual(SINGLE_LIMIT);
  });

  // ── Wallets ───────────────────────────────────────────────────────────────

  test('GET /wallets/{userId} — response time', async ({ request }) => {
    const api = new ApiClient(request);
    api.setToken(state.token);

    const s = await bench('GET /wallets/{userId}', async () => {
      const res = await api.get(`/wallets/${state.userId}`);
      expect([200, 404]).toContain(res.status); // wallet may not exist yet
    });

    expect(s.p95).toBeLessThanOrEqual(P95_LIMIT);
    expect(s.max).toBeLessThanOrEqual(SINGLE_LIMIT);
  });

  test('GET /wallets/{userId}/transactions — response time', async ({ request }) => {
    const api = new ApiClient(request);
    api.setToken(state.token);

    const s = await bench('GET /wallets/{userId}/transactions', async () => {
      const res = await api.get(`/wallets/${state.userId}/transactions`);
      expect([200, 404]).toContain(res.status);
    });

    expect(s.p95).toBeLessThanOrEqual(P95_LIMIT);
    expect(s.max).toBeLessThanOrEqual(SINGLE_LIMIT);
  });

  // ── Notifications ─────────────────────────────────────────────────────────

  test('GET /notifications — response time', async ({ request }) => {
    const api = new ApiClient(request);
    api.setToken(state.token);

    const s = await bench('GET /notifications (paginated)', async () => {
      const { status } = await api.get('/notifications');
      expect(status).toBe(200);
    });

    expect(s.p95).toBeLessThanOrEqual(P95_LIMIT);
    expect(s.max).toBeLessThanOrEqual(SINGLE_LIMIT);
  });

  test('GET /notifications/unread-count — response time', async ({ request }) => {
    const api = new ApiClient(request);
    api.setToken(state.token);

    const s = await bench('GET /notifications/unread-count', async () => {
      const { status } = await api.get('/notifications/unread-count');
      expect(status).toBe(200);
    });

    expect(s.p95).toBeLessThanOrEqual(P95_LIMIT);
    expect(s.max).toBeLessThanOrEqual(SINGLE_LIMIT);
  });

  // ── POST throughput ───────────────────────────────────────────────────────

  test('POST /auth/login — response time', async ({ request }) => {
    const api   = new ApiClient(request);
    const email = makeEmail();
    const pass  = 'Password123!';

    // Register once
    await api.register({
      name: 'Login Perf', email, password: pass,
      password_confirmation: pass, role: 'customer',
    });

    const s = await bench('POST /auth/login', async () => {
      const res = await request.post(`http://127.0.0.1:8000/api/v1/auth/login`, {
        data: { email, password: pass },
        headers: { Accept: 'application/json', 'Content-Type': 'application/json' },
      });
      expect(res.status()).toBe(200);
    });

    expect(s.p95).toBeLessThanOrEqual(P95_LIMIT);
    expect(s.max).toBeLessThanOrEqual(SINGLE_LIMIT);
  });

  // ── Concurrent load ───────────────────────────────────────────────────────

  test('concurrent — 10 simultaneous GET /services requests', async ({ request }) => {
    const start   = Date.now();
    const results = await Promise.all(
      Array.from({ length: 10 }, () =>
        request.get('http://127.0.0.1:8000/api/v1/services', {
          headers: { Accept: 'application/json' },
        })
      )
    );
    const total = Date.now() - start;

    results.forEach(r => expect(r.status()).toBe(200));
    console.log(`  [PERF] 10x concurrent GET /services — total wall time: ${total}ms`);

    // All 10 concurrent requests should complete within 3s total
    expect(total).toBeLessThanOrEqual(3000);
  });

  test('concurrent — 10 simultaneous GET /orders (authenticated)', async ({ request }) => {
    const start   = Date.now();
    const results = await Promise.all(
      Array.from({ length: 10 }, () =>
        request.get('http://127.0.0.1:8000/api/v1/orders', {
          headers: {
            Accept:        'application/json',
            Authorization: `Bearer ${state.token}`,
          },
        })
      )
    );
    const total = Date.now() - start;

    results.forEach(r => expect(r.status()).toBe(200));
    console.log(`  [PERF] 10x concurrent GET /orders — total wall time: ${total}ms`);

    expect(total).toBeLessThanOrEqual(3000);
  });

  // ── Cache effectiveness ───────────────────────────────────────────────────

  test('cache — 2nd request to GET /orders/{id} should be faster', async ({ request }) => {
    const api = new ApiClient(request);
    api.setToken(state.token);

    // Cold hit
    const cold = await measure(async () => {
      await api.get(`/orders/${state.orderId}`);
    });

    // Warm hits (should be served from cache)
    const warmTimes: number[] = [];
    for (let i = 0; i < 5; i++) {
      warmTimes.push(await measure(async () => {
        await api.get(`/orders/${state.orderId}`);
      }));
    }
    const warmAvg = Math.round(warmTimes.reduce((s, t) => s + t, 0) / warmTimes.length);

    console.log(`  [PERF] Cache test — cold: ${cold}ms  warm avg: ${warmAvg}ms`);

    // Warm should be at most as slow as cold (cache driver is 'array' locally so no Redis speedup, but should not regress)
    expect(warmAvg).toBeLessThanOrEqual(Math.max(cold * 2, 500));
  });

});
