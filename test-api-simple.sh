#!/bin/bash

BASE_URL="http://127.0.0.1:8000/api/v1"

echo "================================"
echo "API ENDPOINT TESTING"
echo "================================"
echo ""

# Test 1: Ping endpoint
echo "1. Testing Ping Endpoint..."
curl -s -X GET "$BASE_URL/../ping" | jq '.'
echo ""

# Test 2: Get services (public)
echo "2. Testing Public Services Endpoint..."
curl -s -X GET "$BASE_URL/services" \
  -H "Accept: application/json" | jq '.'
echo ""

# Test 3: Register (will fail without DB)
echo "3. Testing Registration Endpoint..."
curl -s -X POST "$BASE_URL/auth/register" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "name": "Test User",
    "email": "test@example.com",
    "password": "SecureP@ss123",
    "password_confirmation": "SecureP@ss123",
    "role": "customer"
  }' | jq '.'
echo ""

# Test 4: Unauthenticated access
echo "4. Testing Unauthenticated Access..."
curl -s -X GET "$BASE_URL/auth/me" \
  -H "Accept: application/json" | jq '.'
echo ""

# Test 5: Invalid token
echo "5. Testing Invalid Token..."
curl -s -X GET "$BASE_URL/auth/me" \
  -H "Authorization: Bearer invalid_token" \
  -H "Accept: application/json" | jq '.'
echo ""

# Test 6: Check security headers
echo "6. Checking Security Headers..."
curl -s -I "$BASE_URL/auth/me" | grep -E "X-Frame-Options|X-Content-Type-Options|X-Request-ID"
echo ""

echo "================================"
echo "TESTS COMPLETE"
echo "================================"
