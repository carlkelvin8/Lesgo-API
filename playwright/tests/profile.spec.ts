import { test, expect } from '@playwright/test';
import { ApiClient, makeEmail } from '../lib/api-client';

const state = {
  token:        '',
  email:        '',
  referralCode: '',
};

test.describe.serial('Profile Fields', () => {

  test('POST /auth/register → includes new profile fields', async ({ request }) => {
    const api = new ApiClient(request);
    state.email = makeEmail();

    const res = await api.register({
      name:                  'Profile Test User',
      email:                 state.email,
      password:              'Password123!',
      password_confirmation: 'Password123!',
      role:                  'customer',
      phone_number:          '+639171234567',
    });

    expect(res.success).toBe(true);
    state.token = res.token;

    // New profile fields should be in response
    expect(res.user).toHaveProperty('referral_code');
    expect(res.user).toHaveProperty('points');
    expect(res.user).toHaveProperty('date_of_birth');
    expect(res.user).toHaveProperty('address_line1');
    expect(res.user).toHaveProperty('address_line2');
    expect(res.user).toHaveProperty('profile_photo_url');

    // Auto-generated referral code
    expect(typeof res.user.referral_code).toBe('string');
    expect((res.user.referral_code as string).length).toBeGreaterThan(0);
    state.referralCode = res.user.referral_code as string;

    // Points start at 0
    expect(res.user.points).toBe(0);
  });

  test('GET /auth/me → returns all profile fields', async ({ request }) => {
    const api = new ApiClient(request);
    api.setToken(state.token);

    const { status, body } = await api.get('/auth/me');
    expect(status).toBe(200);

    const user = (body as any).user;
    expect(user).toHaveProperty('referral_code');
    expect(user).toHaveProperty('points');
    expect(user).toHaveProperty('date_of_birth');
    expect(user).toHaveProperty('address_line1');
    expect(user).toHaveProperty('profile_photo_url');
  });

  test('PUT /auth/me → updates date_of_birth and address fields', async ({ request }) => {
    const api = new ApiClient(request);
    api.setToken(state.token);

    const { status, body } = await api.put('/auth/me', {
      date_of_birth:  '1995-12-18',
      address_line1:  '123 Rizal St, Manila',
      address_line2:  'Unit 4B',
      profile_photo_url: 'https://cdn.example.com/photos/test.jpg',
    });

    expect(status).toBe(200);
    const user = (body as any).user;
    // API may return full ISO datetime or date-only string
    expect(user.date_of_birth).toMatch(/^1995-12-18/);
    expect(user.address_line1).toBe('123 Rizal St, Manila');
    expect(user.address_line2).toBe('Unit 4B');
    expect(user.profile_photo_url).toBe('https://cdn.example.com/photos/test.jpg');
  });

  test('PUT /auth/me → 422 invalid date_of_birth', async ({ request }) => {
    const api = new ApiClient(request);
    api.setToken(state.token);

    const { status } = await api.put('/auth/me', {
      date_of_birth: 'not-a-date',
    });
    expect(status).toBe(422);
  });

  test('PUT /auth/me → 422 future date_of_birth', async ({ request }) => {
    const api = new ApiClient(request);
    api.setToken(state.token);

    const { status } = await api.put('/auth/me', {
      date_of_birth: '2099-01-01',
    });
    expect(status).toBe(422);
  });

  test('POST /auth/register with referred_by → awards points', async ({ request }) => {
    test.skip(!state.referralCode, 'No referral code available');

    const api = new ApiClient(request);
    const res = await api.register({
      name:                  'Referred User',
      email:                 makeEmail(),
      password:              'Password123!',
      password_confirmation: 'Password123!',
      role:                  'customer',
      // @ts-ignore — extra field
      referred_by:           state.referralCode,
    });

    expect(res.success).toBe(true);
    // New user gets 5 referral points
    expect(res.user.points).toBe(5);
  });

  test('POST /auth/register → 422 invalid referred_by code', async ({ request }) => {
    const api = new ApiClient(request);
    const res = await api.register({
      name:                  'Bad Referral',
      email:                 makeEmail(),
      password:              'Password123!',
      password_confirmation: 'Password123!',
      role:                  'customer',
      // @ts-ignore
      referred_by:           'INVALIDCODE999',
    });
    // Should fail validation since code doesn't exist
    expect(res.success).toBe(false);
  });
});
