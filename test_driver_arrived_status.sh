#!/bin/bash

# Test script for Driver Arrived Status feature
# This script tests the new driver_arrived_at_pickup status

echo "=== Testing Driver Arrived Status Feature ==="
echo ""

# Configuration
API_URL="http://localhost:8000/api/v1"
ORDER_ID="${1:-1}"  # Use first argument or default to 1

echo "Testing order ID: $ORDER_ID"
echo ""

# Test 1: Get current order status
echo "1. Getting current order status..."
curl -s -X GET "$API_URL/orders/$ORDER_ID" \
  -H "Authorization: Bearer YOUR_DRIVER_TOKEN_HERE" \
  -H "Content-Type: application/json" | jq '.data | {id, status, accepted_at, driver_arrived_at_pickup_at, picked_up_at}'

echo ""
echo ""

# Test 2: Update status to driver_arrived_at_pickup
echo "2. Updating status to 'driver_arrived_at_pickup'..."
curl -s -X PATCH "$API_URL/orders/$ORDER_ID/status" \
  -H "Authorization: Bearer YOUR_DRIVER_TOKEN_HERE" \
  -H "Content-Type: application/json" \
  -d '{"status": "driver_arrived_at_pickup"}' | jq '.'

echo ""
echo ""

# Test 3: Verify the status was updated
echo "3. Verifying status update..."
curl -s -X GET "$API_URL/orders/$ORDER_ID" \
  -H "Authorization: Bearer YOUR_DRIVER_TOKEN_HERE" \
  -H "Content-Type: application/json" | jq '.data | {id, status, accepted_at, driver_arrived_at_pickup_at, picked_up_at}'

echo ""
echo ""
echo "=== Test Complete ==="
echo ""
echo "Expected results:"
echo "- Status should be 'driver_arrived_at_pickup'"
echo "- driver_arrived_at_pickup_at should have a timestamp"
echo "- Customer should receive a real-time notification"
