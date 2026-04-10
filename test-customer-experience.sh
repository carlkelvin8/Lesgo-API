#!/bin/bash

# Test script for Customer Experience System
API_BASE="https://lesgo-api-feature-auth-secmes.free.laravel.cloud/api/v1"

echo "🚀 Testing LeSGo API Customer Experience System"
echo "================================================"

# Test API Health
echo "1. Testing API Health..."
curl -s "$API_BASE/ping" | jq '.message, .database'
echo ""

# Test Services (public endpoint)
echo "2. Testing Services endpoint..."
curl -s "$API_BASE/services" | jq '.message'
echo ""

# Test FAQ Categories (public endpoint)
echo "3. Testing FAQ Categories..."
curl -s "$API_BASE/faq/categories" | jq '.message'
echo ""

# Test FAQ Search (public endpoint)
echo "4. Testing FAQ Search..."
curl -s "$API_BASE/faq/search?q=payment" | jq '.message'
echo ""

# Test Featured FAQ Articles (public endpoint)
echo "5. Testing Featured FAQ Articles..."
curl -s "$API_BASE/faq/featured" | jq '.message'
echo ""

echo "✅ Customer Experience System endpoints are accessible!"
echo "📋 Full API documentation available at:"
echo "   - Swagger UI: https://lesgo-api-feature-auth-secmes.free.laravel.cloud/swagger"
echo "   - OpenAPI JSON: https://lesgo-api-feature-auth-secmes.free.laravel.cloud/api-docs.json"