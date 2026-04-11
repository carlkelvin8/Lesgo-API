import { test, expect } from '@playwright/test';
import { ApiClient, makeEmail } from '../lib/api-client';

test('simple registration test', async ({ request }) => {
  const api = new ApiClient(request);
  
  const response = await api.register({
    name: 'Test User',
    email: makeEmail(),
    password: 'Password123!',
    password_confirmation: 'Password123!',
    role: 'customer',
    phone_number: '+639171234567'
  });
  
  console.log('Registration response:', JSON.stringify(response, null, 2));
  
  expect(response.success).toBe(true);
  expect(response.user).toBeDefined();
  expect(response.user.id).toBeDefined();
  expect(response.token).toBeDefined();
});