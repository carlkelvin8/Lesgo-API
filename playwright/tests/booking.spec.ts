import { test, expect } from '@playwright/test';
import { ApiClient, makeEmail } from '../lib/api-client';

const state = {
  customerToken: '',
  customerId:    0,
  serviceId:     0,
  orderId:       0,
};

test.describe.serial('Booking Flow (LesGo)', () => {

  test('setup', async ({ request }) => {
    const api = new ApiClient(request);
    const r = await api.register({
      name: 'Booking User', email: makeEmail(),
      password: 'Password123!', password_confirmation: 'Password123!',
      role: 'customer',
    });
    state.customerToken = r.token;
    state.customerId    = r.user.id;

    const { body: svcList } = await api.get('/services');
    const services = (svcList.data ?? []) as any[];
    if (services.length > 0) state.serviceId = services[0].id;

    expect(r.success).toBe(true);
  });

  // ── Full booking summary fields ─────────────────────────────────────────────

  test('POST /orders → saves all booking summary fields', async ({ request }) => {
    test.skip(!state.serviceId, 'No services seeded');

    const api = new ApiClient(request);
    api.setToken(state.customerToken);

    const { status, body } = await api.post('/orders', {
      service_id:           state.serviceId,
      estimated_distance_m: 2100,
      payment_method:       'cash',
      vehicle_type:         'motor',
      passenger_name:       'Karlosrivo',
      notes:                'No notes added',
      item_description:     'Books, Clothes',
      estimated_weight_kg:  3,
      pickup: {
        address:       '1600 Amphitheatre Pkwy, Mountain View',
        lat:           37.4224,
        lng:           -122.0840,
        contact_name:  'Karlosrivo',
        contact_phone: '+639171234567',
      },
      dropoff: {
        address:       '1904 Colony Street, Mountain View',
        lat:           37.4300,
        lng:           -122.0900,
        contact_name:  'Maria Santos',
        contact_phone: '+639181234567',
      },
    });

    expect([201, 500]).toContain(status);
    expect(body.success).toBe(true);

    if (status !== 201) return;
    const order = body.data as any;
    state.orderId = order.id;

    // All booking summary fields saved
    expect(order.status).toBe('pending');
    expect(order.payment_method).toBe('cash');
    expect(order.vehicle_type).toBe('motor');
    expect(order.passenger_name).toBe('Karlosrivo');
    expect(order.notes).toBe('No notes added');
    expect(order.item_description).toBe('Books, Clothes');
    expect(parseFloat(order.estimated_weight_kg)).toBe(3);

    // Inline address fields saved
    expect(order.pickup_address).toBe('1600 Amphitheatre Pkwy, Mountain View');
    expect(order.dropoff_address).toBe('1904 Colony Street, Mountain View');
    expect(order.pickup_contact_name).toBe('Karlosrivo');
    expect(order.dropoff_contact_name).toBe('Maria Santos');
    expect(parseFloat(order.pickup_lat)).toBeCloseTo(37.4224, 3);
    expect(parseFloat(order.dropoff_lat)).toBeCloseTo(37.4300, 3);

    // Fare breakdown saved
    expect(order).toHaveProperty('fare_breakdown');
    expect(order.fare_breakdown).toHaveProperty('base_fare');
    expect(order.fare_breakdown).toHaveProperty('total');
    expect(parseFloat(order.estimated_fare)).toBeGreaterThan(0);

    // Discount defaults to 0
    expect(parseFloat(order.discount_amount ?? '0')).toBe(0);
  });

  test('POST /orders → saves items (LesBuy)', async ({ request }) => {
    test.skip(!state.serviceId, 'No services seeded');

    const api = new ApiClient(request);
    api.setToken(state.customerToken);

    const { status, body } = await api.post('/orders', {
      service_id:           state.serviceId,
      estimated_distance_m: 3000,
      payment_method:       'gcash',
      pickup:  { address: 'Pickup', lat: 14.5995, lng: 120.9842 },
      dropoff: { address: 'Dropoff', lat: 14.5547, lng: 121.0244 },
      items: [
        {
          name:            'Face Mask (50pcs)',
          quantity:        1,
          unit:            'pack',
          notes:           '3-ply surgical',
          estimated_price: 250.00,
        },
        {
          name:            'Facial Cleanser',
          quantity:        2,
          unit:            'bottle',
          estimated_price: 299.00,
        },
      ],
      meta: { order_value: 848 },
    });

    expect([201, 500]).toContain(status);
    if (status !== 201) return;
    const order = body.data as any;

    // Items embedded in response (as lesbuy_items)
    const items = order.lesbuy_items ?? order.items ?? [];
    expect(Array.isArray(items)).toBe(true);
    expect(items.length).toBe(2);

    const item1 = items[0];
    expect(item1.name).toBe('Face Mask (50pcs)');
    expect(item1.quantity).toBe(1);
    expect(item1.unit).toBe('pack');
    expect(item1.notes).toBe('3-ply surgical');
    expect(parseFloat(item1.estimated_price)).toBe(250);
    expect(item1.status).toBe('pending');
  });

  test('POST /orders → voucher_code saved', async ({ request }) => {
    test.skip(!state.serviceId, 'No services seeded');

    const api = new ApiClient(request);
    api.setToken(state.customerToken);

    const { status, body } = await api.post('/orders', {
      service_id:           state.serviceId,
      estimated_distance_m: 2000,
      payment_method:       'gcash',
      voucher_code:         'SAVE10',
      pickup:  { address: 'A', lat: 14.5995, lng: 120.9842 },
      dropoff: { address: 'B', lat: 14.5547, lng: 121.0244 },
    });

    expect(status).toBe(201);
    const order = body.data as any;
    expect(order.voucher_code).toBe('SAVE10');
  });

  test('GET /orders/{id} → returns all booking fields', async ({ request }) => {
    test.skip(!state.orderId, 'No order created');

    const api = new ApiClient(request);
    api.setToken(state.customerToken);

    const { status, body } = await api.get(`/orders/${state.orderId}`);
    expect(status).toBe(200);

    const order = body.data as any;
    expect(order).toHaveProperty('pickup_address');
    expect(order).toHaveProperty('dropoff_address');
    expect(order).toHaveProperty('pickup_lat');
    expect(order).toHaveProperty('dropoff_lat');
    expect(order).toHaveProperty('fare_breakdown');
    expect(order).toHaveProperty('vehicle_type');
    expect(order).toHaveProperty('passenger_name');
    expect(order).toHaveProperty('service');
    expect(order).toHaveProperty('customer');
    expect(order).toHaveProperty('lesbuy_items');
    expect(order).toHaveProperty('payments');
  });

  // ── Validation ──────────────────────────────────────────────────────────────

  test('POST /orders → 422 invalid vehicle_type', async ({ request }) => {
    test.skip(!state.serviceId, 'No services seeded');

    const api = new ApiClient(request);
    api.setToken(state.customerToken);

    const { status } = await api.post('/orders', {
      service_id:           state.serviceId,
      estimated_distance_m: 2000,
      vehicle_type:         'helicopter', // invalid
      pickup:  { address: 'A', lat: 14.5995, lng: 120.9842 },
      dropoff: { address: 'B', lat: 14.5547, lng: 121.0244 },
    });
    expect(status).toBe(422);
  });

  test('POST /orders → 422 invalid payment_method', async ({ request }) => {
    test.skip(!state.serviceId, 'No services seeded');

    const api = new ApiClient(request);
    api.setToken(state.customerToken);

    const { status } = await api.post('/orders', {
      service_id:           state.serviceId,
      estimated_distance_m: 2000,
      payment_method:       'bitcoin', // invalid
      pickup:  { address: 'A', lat: 14.5995, lng: 120.9842 },
      dropoff: { address: 'B', lat: 14.5547, lng: 121.0244 },
    });
    expect(status).toBe(422);
  });

  test('POST /orders → 422 weight exceeds max', async ({ request }) => {
    test.skip(!state.serviceId, 'No services seeded');

    const api = new ApiClient(request);
    api.setToken(state.customerToken);

    const { status } = await api.post('/orders', {
      service_id:           state.serviceId,
      estimated_distance_m: 2000,
      estimated_weight_kg:  999, // exceeds max 100
      pickup:  { address: 'A', lat: 14.5995, lng: 120.9842 },
      dropoff: { address: 'B', lat: 14.5547, lng: 121.0244 },
    });
    expect(status).toBe(422);
  });
});
