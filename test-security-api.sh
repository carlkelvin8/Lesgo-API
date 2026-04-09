#!/bin/bash

# Test Security API endpoints
BASE_URL="https://lesgo-api-feature-auth-secmes.free.laravel.cloud/api/v1"

echo "=== Testing Security API ==="

# Test 1: Get security dashboard (requires authentication)
echo "1. Testing Security Dashboard..."
curl -s -X GET "$BASE_URL/security/dashboard" \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" | jq '.'

echo -e "\n"

# Test 2: Test 2FA setup (requires authentication)
echo "2. Testing 2FA Setup (should fail without auth)..."
curl -s -X POST "$BASE_URL/security/2fa/setup" \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" | jq '.'

echo -e "\n"

# Test 3: Test GDPR request creation (should fail without auth)
echo "3. Testing GDPR Request Creation (should fail without auth)..."
curl -s -X POST "$BASE_URL/security/gdpr/requests" \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -d '{
    "request_type": "access",
    "description": "I want to access my personal data"
  }' | jq '.'

echo -e "\n"

# Test 4: Test audit logs (should fail without auth)
echo "4. Testing Audit Logs (should fail without auth)..."
curl -s -X GET "$BASE_URL/security/audit/logs" \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" | jq '.'

echo -e "\n"

echo "=== Security API Test Complete ==="
echo "Note: All endpoints should return 401 Unauthorized without proper authentication"