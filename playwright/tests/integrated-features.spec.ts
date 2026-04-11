import { test, expect } from '@playwright/test';
import { ApiClient, makeEmail, assertSuccess, assertError } from '../lib/api-client';

interface VoucherValidation {
  valid: boolean;
  discount_amount?: number;
  discount_type?: string;
  new_total?: number;
  error?: string;
  voucher_details?: any;
}

interface PredictiveETA {
  estimated_arrival: string;
  estimated_minutes: number;
  confidence_level: string;
  factors: any;
  last_updated: string;
}

const state = {
  // Customer
  customerToken: '',
  customerId: 0,
  
  // Driver with sufficient balance
  driverToken: '',
  driverId: 0,
  driverProfileId: 0,
  
  // Driver with insufficient balance
  poorDriverToken: '',
  poorDriverId: 0,
  poorDriverProfileId: 0,
  
  // Admin
  adminToken: '',
  adminId: 0,
  
  // Test order
  orderId: 0,
  serviceId: 1,
};

test.describe.serial('Integrated Features - Complete Test Suite', () => {
  
  test('setup — create test users', async ({ request }) => {
    const api = new ApiClient(request);
    
    // Create customer
    const customer = await api.register({
      name: 'Test Customer',
      email: makeEmail(),
      password: 'Password123!',
      password_confirmation: 'Password123!',
      role: 'customer',
      phone_number: '+639171234567'
    });
    
    console.log('Customer registration response:', JSON.stringify(customer, null, 2));
    
    state.customerToken = customer.token;
    state.customerId = customer.user?.id || 0;
    
    // Create driver with sufficient balance
    const driverEmail = makeEmail();
    const driverPassword = 'Password123!';
    const driver = await api.post('/drivers/register', {
      name: 'Rich Driver',
      email: driverEmail,
      password: driverPassword,
      password_confirmation: driverPassword,
      phone_number: '+639171234568',
      license_number: 'DL123456789',
      license_expiry_date: '2027-12-31'
    });
    
    if (driver.status === 201 && driver.body.data) {
      state.driverId = driver.body.data.user.id;
      state.driverProfileId = driver.body.data.driver_profile.id;
      
      // Login to get token
      const driverLogin = await api.login({
        email: driverEmail,
        password: driverPassword
      });
      if (driverLogin.success) {
        state.driverToken = driverLogin.token;
      }
    }
    
    // Create driver with insufficient balance
    const poorDriverEmail = makeEmail();
    const poorDriverPassword = 'Password123!';
    const poorDriver = await api.post('/drivers/register', {
      name: 'Poor Driver',
      email: poorDriverEmail,
      password: poorDriverPassword,
      password_confirmation: poorDriverPassword,
      phone_number: '+639171234569',
      license_number: 'DL987654321',
      license_expiry_date: '2027-12-31'
    });
    
    if (poorDriver.status === 201 && poorDriver.body.data) {
      state.poorDriverId = poorDriver.body.data.user.id;
      state.poorDriverProfileId = poorDriver.body.data.driver_profile.id;
      
      // Login to get token
      const poorDriverLogin = await api.login({
        email: poorDriverEmail,
        password: poorDriverPassword
      });
      state.poorDriverToken = poorDriverLogin.token;
    }
    
    // Create partner admin (since 'admin' role is not allowed in registration)
    const admin = await api.register({
      name: 'Test Admin',
      email: makeEmail(),
      password: 'Password123!',
      password_confirmation: 'Password123!',
      role: 'partner_admin',
      phone_number: '+639171234570'
    });
    
    console.log('Admin registration response:', JSON.stringify(admin, null, 2));
    
    state.adminToken = admin.token;
    state.adminId = admin.user?.id || 0;
    
    expect(customer.success).toBe(true);
    expect(driver.status).toBe(201);
    expect(poorDriver.status).toBe(201);
    expect(admin.success).toBe(true);
  });

  test('setup — get available service', async ({ request }) => {
    const api = new ApiClient(request);
    api.setToken(state.customerToken);
    
    const services = await api.get('/services');
    
    if (services.status === 200 && services.body.data) {
      const serviceList = services.body.data as any;
      if (Array.isArray(serviceList) && serviceList.length > 0) {
        state.serviceId = serviceList[0].id;
      }
    }
    
    expect([200, 404]).toContain(services.status);
  });

  // ═══════════════════════════════════════════════════════════════════════════
  // VOUCHER SYSTEM TESTS
  // ═══════════════════════════════════════════════════════════════════════════

  test('GET /vouchers/available → 200 returns available vouchers', async ({ request }) => {
    const api = new ApiClient(request);
    api.setToken(state.customerToken);
    
    const { status, body } = await api.get('/vouchers/available');
    
    expect(status).toBe(200);
    assertSuccess(body);
    expect(Array.isArray(body.data)).toBe(true);
    
    // Check voucher structure
    if ((body.data as any[]).length > 0) {
      const voucher = (body.data as any[])[0];
      expect(voucher).toHaveProperty('code');
      expect(voucher).toHaveProperty('title');
      expect(voucher).toHaveProperty('description');
      expect(voucher).toHaveProperty('eligible');
    }
  });

  test('GET /vouchers/available → 401 without authentication', async ({ request }) => {
    const api = new ApiClient(request);
    // Don't set token
    
    const { status } = await api.get('/vouchers/available');
    
    expect(status).toBe(401);
  });

  test('POST /vouchers/validate → 200 validates WELCOME10 voucher', async ({ request }) => {
    const api = new ApiClient(request);
    api.setToken(state.customerToken);
    
    const { status, body } = await api.post<VoucherValidation>('/vouchers/validate', {
      voucher_code: 'WELCOME10',
      order_value: 150.00
    });
    
    expect(status).toBe(200);
    assertSuccess(body);
    
    const validation = body.data!;
    expect(validation).toHaveProperty('valid');
    expect(typeof validation.valid).toBe('boolean');
    
    if (validation.valid) {
      expect(validation).toHaveProperty('discount_amount');
      expect(validation).toHaveProperty('discount_type');
      expect(validation).toHaveProperty('new_total');
      expect(typeof validation.discount_amount).toBe('number');
      expect(validation.discount_amount).toBeGreaterThan(0);
    }
  });

  test('POST /vouchers/validate → 200 rejects invalid voucher', async ({ request }) => {
    const api = new ApiClient(request);
    api.setToken(state.customerToken);
    
    const { status, body } = await api.post<VoucherValidation>('/vouchers/validate', {
      voucher_code: 'INVALID123',
      order_value: 150.00
    });
    
    expect(status).toBe(200);
    assertSuccess(body);
    
    const validation = body.data!;
    expect(validation.valid).toBe(false);
    expect(validation).toHaveProperty('error');
    expect(typeof validation.error).toBe('string');
  });

  test('POST /vouchers/validate → 422 with invalid input', async ({ request }) => {
    const api = new ApiClient(request);
    api.setToken(state.customerToken);
    
    const { status } = await api.post('/vouchers/validate', {
      voucher_code: '', // Empty code
      order_value: -10 // Negative value
    });
    
    expect(status).toBe(422);
  });

  // ═══════════════════════════════════════════════════════════════════════════
  // ENHANCED ORDER CREATION WITH AUTO-ASSIGNMENT
  // ═══════════════════════════════════════════════════════════════════════════

  test('POST /orders → 201 creates order with auto-assignment', async ({ request }) => {
    const api = new ApiClient(request);
    api.setToken(state.customerToken);
    
    const orderData = {
      service_id: state.serviceId,
      pickup: {
        address: 'Makati City, Metro Manila',
        lat: 14.5547,
        lng: 121.0244,
        contact_name: 'Test Customer',
        contact_phone: '+639171234567'
      },
      dropoff: {
        address: 'BGC, Taguig City, Metro Manila',
        lat: 14.5515,
        lng: 121.0473,
        contact_name: 'Test Recipient',
        contact_phone: '+639171234568'
      },
      estimated_distance_m: 5000,
      payment_method: 'cash',
      notes: 'Test order for auto-assignment'
    };
    
    const { status, body } = await api.post('/orders', orderData);
    
    expect([201, 422]).toContain(status);
    
    if (status === 201) {
      assertSuccess(body);
      const order = body.data as any;
      state.orderId = order.id;
      
      expect(order).toHaveProperty('id');
      expect(order).toHaveProperty('status');
      expect(order.status).toBe('pending');
      expect(order).toHaveProperty('customer_id');
      expect(order.customer_id).toBe(state.customerId);
    }
  });

  test('POST /orders → 201 creates order with valid voucher', async ({ request }) => {
    const api = new ApiClient(request);
    api.setToken(state.customerToken);
    
    const orderData = {
      service_id: state.serviceId,
      pickup: {
        address: 'Quezon City, Metro Manila',
        lat: 14.6760,
        lng: 121.0437,
        contact_name: 'Test Customer',
        contact_phone: '+639171234567'
      },
      dropoff: {
        address: 'Manila City, Metro Manila',
        lat: 14.5995,
        lng: 120.9842,
        contact_name: 'Test Recipient',
        contact_phone: '+639171234568'
      },
      estimated_distance_m: 8000,
      payment_method: 'cash',
      voucher_code: 'WELCOME10',
      notes: 'Test order with voucher'
    };
    
    const { status, body } = await api.post('/orders', orderData);
    
    expect([201, 422]).toContain(status);
    
    if (status === 201) {
      assertSuccess(body);
      const order = body.data as any;
      
      expect(order).toHaveProperty('voucher_code');
      expect(order.voucher_code).toBe('WELCOME10');
      expect(order).toHaveProperty('discount_amount');
      expect(order.discount_amount).toBeGreaterThan(0);
    }
  });

  test('POST /orders → 422 with invalid voucher', async ({ request }) => {
    const api = new ApiClient(request);
    api.setToken(state.customerToken);
    
    const orderData = {
      service_id: state.serviceId,
      pickup: {
        address: 'Makati City, Metro Manila',
        lat: 14.5547,
        lng: 121.0244,
        contact_name: 'Test Customer',
        contact_phone: '+639171234567'
      },
      dropoff: {
        address: 'BGC, Taguig City, Metro Manila',
        lat: 14.5515,
        lng: 121.0473,
        contact_name: 'Test Recipient',
        contact_phone: '+639171234568'
      },
      estimated_distance_m: 5000,
      payment_method: 'cash',
      voucher_code: 'INVALID123',
      notes: 'Test order with invalid voucher'
    };
    
    const { status, body } = await api.post('/orders', orderData);
    
    expect(status).toBe(422);
    assertError(body);
  });

  // ═══════════════════════════════════════════════════════════════════════════
  // ENHANCED ORDER TRACKING WITH PREDICTIVE ETA
  // ═══════════════════════════════════════════════════════════════════════════

  test('GET /tracking/orders/{id} → 200 returns enhanced tracking with predictive ETA', async ({ request }) => {
    if (!state.orderId) {
      test.skip('No test order available');
      return;
    }
    
    const api = new ApiClient(request);
    api.setToken(state.customerToken);
    
    const { status, body } = await api.get(`/tracking/orders/${state.orderId}`);
    
    expect([200, 403, 404]).toContain(status);
    
    if (status === 200) {
      assertSuccess(body);
      const trackingData = body.data as any;
      
      // Check basic tracking structure
      expect(trackingData).toHaveProperty('order');
      expect(trackingData).toHaveProperty('current_status');
      expect(trackingData).toHaveProperty('tracking_events');
      expect(trackingData).toHaveProperty('timeline');
      
      // Check enhanced ETA features
      expect(trackingData).toHaveProperty('eta_details');
      
      const etaDetails = trackingData.eta_details;
      expect(etaDetails).toHaveProperty('estimated_arrival');
      expect(etaDetails).toHaveProperty('estimated_minutes');
      expect(etaDetails).toHaveProperty('confidence_level');
      expect(etaDetails).toHaveProperty('factors');
      expect(etaDetails).toHaveProperty('last_updated');
      
      // Validate ETA data types
      expect(typeof etaDetails.estimated_minutes).toBe('number');
      expect(['high', 'medium', 'low']).toContain(etaDetails.confidence_level);
      expect(typeof etaDetails.factors).toBe('object');
      
      // Check order has predictive ETA
      const order = trackingData.order;
      expect(order).toHaveProperty('predictive_eta');
      
      const predictiveETA = order.predictive_eta;
      expect(predictiveETA).toHaveProperty('estimated_arrival');
      expect(predictiveETA).toHaveProperty('estimated_minutes');
      expect(predictiveETA).toHaveProperty('confidence_level');
    }
  });

  test('GET /tracking/orders/{id} → 403 unauthorized access', async ({ request }) => {
    if (!state.orderId) {
      test.skip('No test order available');
      return;
    }
    
    const api = new ApiClient(request);
    api.setToken(state.driverToken); // Different user
    
    const { status } = await api.get(`/tracking/orders/${state.orderId}`);
    
    expect([403, 404]).toContain(status);
  });

  test('GET /tracking/orders/999999 → 404 non-existent order', async ({ request }) => {
    const api = new ApiClient(request);
    api.setToken(state.customerToken);
    
    const { status } = await api.get('/tracking/orders/999999');
    
    expect(status).toBe(404);
  });

  // ═══════════════════════════════════════════════════════════════════════════
  // DRIVER AUTO-ASSIGNMENT TESTS
  // ═══════════════════════════════════════════════════════════════════════════

  test('PATCH /orders/{id}/status → 422 driver with insufficient balance cannot accept', async ({ request }) => {
    if (!state.orderId) {
      test.skip('No test order available');
      return;
    }
    
    const api = new ApiClient(request);
    api.setToken(state.poorDriverToken);
    
    // First check if driver has insufficient balance
    const walletCheck = await api.get('/wallets/my/validation');
    
    if (walletCheck.status === 200 && walletCheck.body.data) {
      const validation = walletCheck.body.data as any;
      
      if (!validation.has_sufficient_balance) {
        // Try to accept order with insufficient balance
        const { status, body } = await api.patch(`/orders/${state.orderId}/status`, {
          status: 'accepted'
        });
        
        expect(status).toBe(422);
        assertError(body);
        expect(body.message).toContain('Insufficient wallet balance');
        expect(body.data).toHaveProperty('wallet_validation');
      } else {
        test.skip('Driver has sufficient balance, cannot test insufficient balance scenario');
      }
    }
  });

  test('PATCH /orders/{id}/status → 200 driver with sufficient balance can accept', async ({ request }) => {
    if (!state.orderId) {
      test.skip('No test order available');
      return;
    }
    
    const api = new ApiClient(request);
    api.setToken(state.driverToken);
    
    // First check if driver has sufficient balance
    const walletCheck = await api.get('/wallets/my/validation');
    
    if (walletCheck.status === 200 && walletCheck.body.data) {
      const validation = walletCheck.body.data as any;
      
      if (validation.has_sufficient_balance) {
        // Try to accept order with sufficient balance
        const { status, body } = await api.patch(`/orders/${state.orderId}/status`, {
          status: 'accepted'
        });
        
        expect([200, 409]).toContain(status); // 409 if already accepted
        
        if (status === 200) {
          assertSuccess(body);
          const order = body.data as any;
          expect(order.status).toBe('accepted');
          expect(order.driver_id).toBe(state.driverProfileId);
        }
      } else {
        test.skip('Driver has insufficient balance, cannot test sufficient balance scenario');
      }
    }
  });

  // ═══════════════════════════════════════════════════════════════════════════
  // WALLET VALIDATION INTEGRATION TESTS
  // ═══════════════════════════════════════════════════════════════════════════

  test('GET /wallets/my/validation → 200 returns wallet validation', async ({ request }) => {
    const api = new ApiClient(request);
    api.setToken(state.driverToken);
    
    const { status, body } = await api.get('/wallets/my/validation');
    
    expect(status).toBe(200);
    assertSuccess(body);
    
    const validation = body.data as any;
    expect(validation).toHaveProperty('has_sufficient_balance');
    expect(validation).toHaveProperty('current_balance');
    expect(validation).toHaveProperty('minimum_threshold');
    expect(validation).toHaveProperty('shortfall');
    
    expect(typeof validation.has_sufficient_balance).toBe('boolean');
    expect(typeof validation.current_balance).toBe('number');
    expect(typeof validation.minimum_threshold).toBe('number');
    expect(typeof validation.shortfall).toBe('number');
  });

  test('GET /wallets/threshold → 200 returns minimum threshold', async ({ request }) => {
    const api = new ApiClient(request);
    api.setToken(state.customerToken);
    
    const { status, body } = await api.get('/wallets/threshold');
    
    expect(status).toBe(200);
    assertSuccess(body);
    
    const data = body.data as any;
    expect(data).toHaveProperty('minimum_threshold');
    expect(typeof data.minimum_threshold).toBe('number');
    expect(data.minimum_threshold).toBeGreaterThanOrEqual(0);
  });

  // ═══════════════════════════════════════════════════════════════════════════
  // ADMIN WALLET SETTINGS TESTS
  // ═══════════════════════════════════════════════════════════════════════════

  test('GET /admin/wallet-settings/threshold → 200 admin can get threshold', async ({ request }) => {
    const api = new ApiClient(request);
    api.setToken(state.adminToken);
    
    const { status, body } = await api.get('/admin/wallet-settings/threshold');
    
    expect(status).toBe(200);
    assertSuccess(body);
    
    const data = body.data as any;
    expect(data).toHaveProperty('minimum_threshold');
    expect(typeof data.minimum_threshold).toBe('number');
  });

  test('GET /admin/wallet-settings/threshold → 403 non-admin cannot get threshold', async ({ request }) => {
    const api = new ApiClient(request);
    api.setToken(state.customerToken);
    
    const { status } = await api.get('/admin/wallet-settings/threshold');
    
    expect(status).toBe(403);
  });

  test('PUT /admin/wallet-settings/threshold → 200 admin can update threshold', async ({ request }) => {
    const api = new ApiClient(request);
    api.setToken(state.adminToken);
    
    const newThreshold = 125.00;
    const { status, body } = await api.put('/admin/wallet-settings/threshold', {
      threshold: newThreshold
    });
    
    expect(status).toBe(200);
    assertSuccess(body);
    
    const data = body.data as any;
    expect(data.minimum_threshold).toBe(newThreshold);
    
    // Verify the change was applied
    const verification = await api.get('/admin/wallet-settings/threshold');
    expect(verification.status).toBe(200);
    expect((verification.body.data as any).minimum_threshold).toBe(newThreshold);
  });

  test('PUT /admin/wallet-settings/threshold → 403 non-admin cannot update threshold', async ({ request }) => {
    const api = new ApiClient(request);
    api.setToken(state.driverToken);
    
    const { status } = await api.put('/admin/wallet-settings/threshold', {
      threshold: 150.00
    });
    
    expect(status).toBe(403);
  });

  test('PUT /admin/wallet-settings/threshold → 422 invalid threshold values', async ({ request }) => {
    const api = new ApiClient(request);
    api.setToken(state.adminToken);
    
    // Test negative threshold
    const negative = await api.put('/admin/wallet-settings/threshold', {
      threshold: -10.00
    });
    expect(negative.status).toBe(422);
    
    // Test extremely high threshold
    const tooHigh = await api.put('/admin/wallet-settings/threshold', {
      threshold: 50000.00
    });
    expect(tooHigh.status).toBe(422);
  });

  // ═══════════════════════════════════════════════════════════════════════════
  // ERROR HANDLING AND EDGE CASES
  // ═══════════════════════════════════════════════════════════════════════════

  test('authentication required for all protected endpoints', async ({ request }) => {
    const api = new ApiClient(request);
    // Don't set token
    
    const endpoints = [
      { method: 'GET', path: '/vouchers/available' },
      { method: 'POST', path: '/vouchers/validate' },
      { method: 'GET', path: '/wallets/my/validation' },
      { method: 'GET', path: '/wallets/threshold' },
      { method: 'GET', path: '/admin/wallet-settings/threshold' },
      { method: 'PUT', path: '/admin/wallet-settings/threshold' }
    ];
    
    for (const endpoint of endpoints) {
      let response;
      
      if (endpoint.method === 'GET') {
        response = await api.get(endpoint.path);
      } else if (endpoint.method === 'POST') {
        response = await api.post(endpoint.path, {});
      } else if (endpoint.method === 'PUT') {
        response = await api.put(endpoint.path, {});
      }
      
      expect(response!.status).toBe(401);
    }
  });

  test('malformed requests return appropriate errors', async ({ request }) => {
    const api = new ApiClient(request);
    api.setToken(state.customerToken);
    
    // Test malformed voucher validation
    const malformedVoucher = await api.post('/vouchers/validate', {
      // Missing required fields
    });
    expect(malformedVoucher.status).toBe(422);
    
    // Test malformed order creation
    const malformedOrder = await api.post('/orders', {
      // Missing required fields
    });
    expect(malformedOrder.status).toBe(422);
  });

  test('cleanup — reset threshold to default', async ({ request }) => {
    const api = new ApiClient(request);
    api.setToken(state.adminToken);
    
    // Reset to default threshold
    const { status } = await api.put('/admin/wallet-settings/threshold', {
      threshold: 100.00
    });
    
    expect(status).toBe(200);
  });
});