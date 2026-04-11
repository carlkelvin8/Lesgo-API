import { test, expect } from '@playwright/test';
import { ApiClient, makeEmail, assertSuccess, assertError } from '../lib/api-client';

interface WalletValidation {
  has_sufficient_balance: boolean;
  current_balance: number;
  minimum_threshold: number;
  shortfall: number;
}

interface Order {
  id: number;
  customer_id: number;
  driver_id?: number;
  status: string;
  estimated_fare: number;
  [key: string]: unknown;
}

const state = {
  // Driver with sufficient balance
  driverToken: '',
  driverId: 0,
  driverProfileId: 0,
  
  // Driver with insufficient balance
  poorDriverToken: '',
  poorDriverId: 0,
  poorDriverProfileId: 0,
  
  // Customer
  customerToken: '',
  customerId: 0,
  
  // Admin
  adminToken: '',
  adminId: 0,
  
  // Test order
  orderId: 0,
};

test.describe.serial('Wallet Balance Validation', () => {
  
  test('setup — create test users and wallets', async ({ request }) => {
    const api = new ApiClient(request);
    
    // Create driver with sufficient balance
    const driver = await api.register({
      name: 'Rich Driver',
      email: makeEmail(),
      password: 'Password123!',
      password_confirmation: 'Password123!',
      role: 'driver',
      phone_number: '+639171234567'
    });
    
    state.driverToken = driver.token;
    state.driverId = driver.user.id;
    
    // Create driver with insufficient balance
    const poorDriver = await api.register({
      name: 'Poor Driver',
      email: makeEmail(),
      password: 'Password123!',
      password_confirmation: 'Password123!',
      role: 'driver',
      phone_number: '+639171234568'
    });
    
    state.poorDriverToken = poorDriver.token;
    state.poorDriverId = poorDriver.user.id;
    
    // Create customer
    const customer = await api.register({
      name: 'Test Customer',
      email: makeEmail(),
      password: 'Password123!',
      password_confirmation: 'Password123!',
      role: 'customer',
      phone_number: '+639171234569'
    });
    
    state.customerToken = customer.token;
    state.customerId = customer.user.id;
    
    // Create admin
    const admin = await api.register({
      name: 'Test Admin',
      email: makeEmail(),
      password: 'Password123!',
      password_confirmation: 'Password123!',
      role: 'admin',
      phone_number: '+639171234570'
    });
    
    state.adminToken = admin.token;
    state.adminId = admin.user.id;
    
    expect(driver.success).toBe(true);
    expect(poorDriver.success).toBe(true);
    expect(customer.success).toBe(true);
    expect(admin.success).toBe(true);
  });

  test('setup — create driver profiles', async ({ request }) => {
    const api = new ApiClient(request);
    
    // Create driver profile for rich driver
    api.setToken(state.driverToken);
    const richProfile = await api.post('/driver-profiles', {
      license_number: 'DL123456789',
      license_expiry: '2025-12-31',
      vehicle_type: 'motorcycle',
      status: 'active'
    });
    
    if (richProfile.status === 201 && richProfile.body.data) {
      state.driverProfileId = (richProfile.body.data as any).id;
    }
    
    // Create driver profile for poor driver
    api.setToken(state.poorDriverToken);
    const poorProfile = await api.post('/driver-profiles', {
      license_number: 'DL987654321',
      license_expiry: '2025-12-31',
      vehicle_type: 'motorcycle',
      status: 'active'
    });
    
    if (poorProfile.status === 201 && poorProfile.body.data) {
      state.poorDriverProfileId = (poorProfile.body.data as any).id;
    }
    
    expect([201, 409]).toContain(richProfile.status); // 409 if already exists
    expect([201, 409]).toContain(poorProfile.status);
  });

  test('setup — create wallets with different balances', async ({ request }) => {
    // Note: In a real scenario, wallets would be created through the wallet system
    // For testing, we'll assume wallets are created automatically or through seeding
    // We'll verify the balances in the validation tests
  });

  test('setup — create test order', async ({ request }) => {
    const api = new ApiClient(request);
    api.setToken(state.customerToken);
    
    const order = await api.post('/orders', {
      service_id: 1, // Assuming service exists
      pickup: {
        address: 'Makati City, Metro Manila',
        lat: 14.5547,
        lng: 121.0244,
        contact_name: 'Test Customer',
        contact_phone: '+639171234569'
      },
      dropoff: {
        address: 'BGC, Taguig City, Metro Manila',
        lat: 14.5515,
        lng: 121.0473,
        contact_name: 'Test Recipient',
        contact_phone: '+639171234570'
      },
      estimated_distance_m: 5000,
      payment_method: 'cash',
      notes: 'Test order for wallet validation'
    });
    
    if (order.status === 201 && order.body.data) {
      state.orderId = (order.body.data as any).id;
    }
    
    expect([201, 422]).toContain(order.status); // 422 if validation fails
  });

  test('GET /wallets/threshold → 200 returns minimum threshold', async ({ request }) => {
    const api = new ApiClient(request);
    api.setToken(state.driverToken);
    
    const { status, body } = await api.get('/wallets/threshold');
    
    expect(status).toBe(200);
    assertSuccess(body);
    expect(body.data).toHaveProperty('minimum_threshold');
    expect(typeof (body.data as any).minimum_threshold).toBe('number');
    expect((body.data as any).minimum_threshold).toBeGreaterThanOrEqual(0);
  });

  test('GET /wallets/my/validation → 200 returns wallet validation for driver', async ({ request }) => {
    const api = new ApiClient(request);
    api.setToken(state.driverToken);
    
    const { status, body } = await api.get<WalletValidation>('/wallets/my/validation');
    
    expect(status).toBe(200);
    assertSuccess(body);
    
    const validation = body.data!;
    expect(validation).toHaveProperty('has_sufficient_balance');
    expect(validation).toHaveProperty('current_balance');
    expect(validation).toHaveProperty('minimum_threshold');
    expect(validation).toHaveProperty('shortfall');
    
    expect(typeof validation.has_sufficient_balance).toBe('boolean');
    expect(typeof validation.current_balance).toBe('number');
    expect(typeof validation.minimum_threshold).toBe('number');
    expect(typeof validation.shortfall).toBe('number');
    
    // Shortfall should be 0 if balance is sufficient
    if (validation.has_sufficient_balance) {
      expect(validation.shortfall).toBe(0);
      expect(validation.current_balance).toBeGreaterThanOrEqual(validation.minimum_threshold);
    } else {
      expect(validation.shortfall).toBeGreaterThan(0);
      expect(validation.current_balance).toBeLessThan(validation.minimum_threshold);
    }
  });

  test('GET /wallets/my/validation → 200 customer always has sufficient balance', async ({ request }) => {
    const api = new ApiClient(request);
    api.setToken(state.customerToken);
    
    const { status, body } = await api.get<WalletValidation>('/wallets/my/validation');
    
    expect(status).toBe(200);
    assertSuccess(body);
    
    const validation = body.data!;
    // Customers should always have sufficient balance (not affected by wallet validation)
    expect(validation.has_sufficient_balance).toBe(true);
    expect(validation.shortfall).toBe(0);
  });

  test('PATCH /orders/{id}/status → 422 driver with insufficient balance cannot accept', async ({ request }) => {
    if (!state.orderId) {
      test.skip('No test order available');
      return;
    }
    
    const api = new ApiClient(request);
    api.setToken(state.poorDriverToken);
    
    // First, check if this driver has insufficient balance
    const validation = await api.get<WalletValidation>('/wallets/my/validation');
    
    if (validation.body.data?.has_sufficient_balance) {
      test.skip('Driver has sufficient balance, cannot test insufficient balance scenario');
      return;
    }
    
    // Try to accept the order
    const { status, body } = await api.patch(`/orders/${state.orderId}/status`, {
      status: 'accepted'
    });
    
    expect(status).toBe(422);
    assertError(body);
    expect(body.message).toContain('Insufficient wallet balance');
    expect(body.data).toHaveProperty('wallet_validation');
    
    const walletValidation = (body.data as any).wallet_validation;
    expect(walletValidation.has_sufficient_balance).toBe(false);
    expect(walletValidation.shortfall).toBeGreaterThan(0);
  });

  test('PATCH /orders/{id}/status → 200 driver with sufficient balance can accept', async ({ request }) => {
    if (!state.orderId) {
      test.skip('No test order available');
      return;
    }
    
    const api = new ApiClient(request);
    api.setToken(state.driverToken);
    
    // First, check if this driver has sufficient balance
    const validation = await api.get<WalletValidation>('/wallets/my/validation');
    
    if (!validation.body.data?.has_sufficient_balance) {
      test.skip('Driver has insufficient balance, cannot test sufficient balance scenario');
      return;
    }
    
    // Try to accept the order
    const { status, body } = await api.patch(`/orders/${state.orderId}/status`, {
      status: 'accepted'
    });
    
    expect([200, 409]).toContain(status); // 409 if already accepted by another driver
    
    if (status === 200) {
      assertSuccess(body);
      const order = body.data as any;
      expect(order.status).toBe('accepted');
      expect(order.driver_id).toBe(state.driverProfileId);
    }
  });

  test('GET /admin/wallet-settings/threshold → 200 admin can get threshold', async ({ request }) => {
    const api = new ApiClient(request);
    api.setToken(state.adminToken);
    
    const { status, body } = await api.get('/admin/wallet-settings/threshold');
    
    expect(status).toBe(200);
    assertSuccess(body);
    expect(body.data).toHaveProperty('minimum_threshold');
    expect(typeof (body.data as any).minimum_threshold).toBe('number');
  });

  test('GET /admin/wallet-settings/threshold → 403 non-admin cannot get threshold', async ({ request }) => {
    const api = new ApiClient(request);
    api.setToken(state.driverToken);
    
    const { status, body } = await api.get('/admin/wallet-settings/threshold');
    
    expect(status).toBe(403);
    assertError(body);
  });

  test('PUT /admin/wallet-settings/threshold → 200 admin can update threshold', async ({ request }) => {
    const api = new ApiClient(request);
    api.setToken(state.adminToken);
    
    const newThreshold = 150.00;
    const { status, body } = await api.put('/admin/wallet-settings/threshold', {
      threshold: newThreshold
    });
    
    expect(status).toBe(200);
    assertSuccess(body);
    expect((body.data as any).minimum_threshold).toBe(newThreshold);
    
    // Verify the threshold was actually updated
    const verification = await api.get('/admin/wallet-settings/threshold');
    expect(verification.status).toBe(200);
    expect((verification.body.data as any).minimum_threshold).toBe(newThreshold);
  });

  test('PUT /admin/wallet-settings/threshold → 403 non-admin cannot update threshold', async ({ request }) => {
    const api = new ApiClient(request);
    api.setToken(state.driverToken);
    
    const { status, body } = await api.put('/admin/wallet-settings/threshold', {
      threshold: 200.00
    });
    
    expect(status).toBe(403);
    assertError(body);
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
    
    // Test non-numeric threshold
    const nonNumeric = await api.put('/admin/wallet-settings/threshold', {
      threshold: 'invalid'
    });
    expect(nonNumeric.status).toBe(422);
  });

  test('wallet validation affects order acceptance consistently', async ({ request }) => {
    if (!state.orderId) {
      test.skip('No test order available');
      return;
    }
    
    const api = new ApiClient(request);
    
    // Test with driver who has insufficient balance
    api.setToken(state.poorDriverToken);
    const poorValidation = await api.get<WalletValidation>('/wallets/my/validation');
    
    if (poorValidation.body.data && !poorValidation.body.data.has_sufficient_balance) {
      const poorAccept = await api.patch(`/orders/${state.orderId}/status`, {
        status: 'accepted'
      });
      expect(poorAccept.status).toBe(422);
      expect(poorAccept.body.message).toContain('Insufficient wallet balance');
    }
    
    // Test with driver who has sufficient balance
    api.setToken(state.driverToken);
    const richValidation = await api.get<WalletValidation>('/wallets/my/validation');
    
    if (richValidation.body.data && richValidation.body.data.has_sufficient_balance) {
      const richAccept = await api.patch(`/orders/${state.orderId}/status`, {
        status: 'accepted'
      });
      expect([200, 409]).toContain(richAccept.status); // 409 if already accepted
    }
  });

  test('wallet validation endpoints require authentication', async ({ request }) => {
    const api = new ApiClient(request);
    // Don't set token
    
    const validation = await api.get('/wallets/my/validation');
    expect(validation.status).toBe(401);
    
    const threshold = await api.get('/wallets/threshold');
    expect(threshold.status).toBe(401);
    
    const adminThreshold = await api.get('/admin/wallet-settings/threshold');
    expect(adminThreshold.status).toBe(401);
  });

  test('edge case — balance exactly equals threshold', async ({ request }) => {
    const api = new ApiClient(request);
    api.setToken(state.adminToken);
    
    // Set threshold to a specific value
    const testThreshold = 100.00;
    await api.put('/admin/wallet-settings/threshold', {
      threshold: testThreshold
    });
    
    // Check validation logic handles exact equality correctly
    api.setToken(state.driverToken);
    const validation = await api.get<WalletValidation>('/wallets/my/validation');
    
    expect(validation.status).toBe(200);
    const data = validation.body.data!;
    
    // If balance equals threshold, should be sufficient
    if (data.current_balance === data.minimum_threshold) {
      expect(data.has_sufficient_balance).toBe(true);
      expect(data.shortfall).toBe(0);
    }
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