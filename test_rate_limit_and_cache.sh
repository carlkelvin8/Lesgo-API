#!/bin/bash

# Test Rate Limiting and Caching Implementation
# Run: bash test_rate_limit_and_cache.sh

echo "========================================="
echo "Testing Rate Limiting & Caching"
echo "========================================="
echo ""

BASE_URL="http://localhost:8000/api/v1"

# Colors
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

echo "1. Testing Rate Limiting (Login Endpoint)"
echo "-----------------------------------------"
echo "Sending 7 login requests (limit is 5 per minute)..."
echo ""

for i in {1..7}; do
  response=$(curl -s -w "\n%{http_code}" -X POST "$BASE_URL/auth/login" \
    -H "Content-Type: application/json" \
    -d '{"email":"test@example.com","password":"wrong"}')
  
  status_code=$(echo "$response" | tail -n1)
  body=$(echo "$response" | head -n-1)
  
  if [ "$status_code" == "429" ]; then
    echo -e "${RED}Request $i: 429 Too Many Requests ✓${NC}"
    echo "Response: $body"
  elif [ "$status_code" == "401" ] || [ "$status_code" == "422" ]; then
    echo -e "${GREEN}Request $i: $status_code (Rate limit not hit yet) ✓${NC}"
  else
    echo -e "${YELLOW}Request $i: $status_code${NC}"
  fi
  
  sleep 0.5
done

echo ""
echo "2. Testing Caching (Partner Menu Endpoint)"
echo "-----------------------------------------"
echo "Note: You need a valid token and partner ID for this test"
echo ""

# You can uncomment and modify this if you have a valid token
# TOKEN="your-token-here"
# PARTNER_ID=1
# 
# echo "First request (should be cache MISS):"
# curl -s -i -X GET "$BASE_URL/partners/$PARTNER_ID/menu" \
#   -H "Authorization: Bearer $TOKEN" | grep "X-Cache-Status"
# 
# echo ""
# echo "Second request (should be cache HIT):"
# curl -s -i -X GET "$BASE_URL/partners/$PARTNER_ID/menu" \
#   -H "Authorization: Bearer $TOKEN" | grep "X-Cache-Status"

echo "========================================="
echo "Test Complete!"
echo "========================================="
echo ""
echo "Summary:"
echo "- Rate limiting: Requests 1-5 should succeed (or 401/422)"
echo "- Rate limiting: Requests 6-7 should return 429"
echo "- Caching: First request = MISS, Second = HIT"
echo ""
echo "Check the full documentation in:"
echo "RATE_LIMITING_AND_CACHING_IMPLEMENTATION.md"
