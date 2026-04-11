#!/bin/bash

# Quick API Endpoint Verification Script
# Tests basic connectivity to all new endpoints

set -e

echo "🔍 Verifying Integrated Feature Endpoints"
echo "========================================="

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

# API Base URL
API_BASE="http://127.0.0.1:8000/api/v1"

# Function to test endpoint
test_endpoint() {
    local method=$1
    local endpoint=$2
    local description=$3
    local expected_status=$4
    local auth_header=$5
    
    echo -e "\n${BLUE}Testing: $description${NC}"
    echo -e "  ${method} ${endpoint}"
    
    local curl_cmd="curl -s -w '%{http_code}' -o /dev/null"
    
    if [ "$method" = "POST" ] || [ "$method" = "PUT" ]; then
        curl_cmd="$curl_cmd -X $method -H 'Content-Type: application/json' -d '{}'"
    elif [ "$method" = "GET" ]; then
        curl_cmd="$curl_cmd -X $method"
    fi
    
    if [ -n "$auth_header" ]; then
        curl_cmd="$curl_cmd -H 'Authorization: Bearer invalid-token'"
    fi
    
    curl_cmd="$curl_cmd ${API_BASE}${endpoint}"
    
    local status_code
    status_code=$(eval $curl_cmd)
    
    if [ "$status_code" = "$expected_status" ]; then
        echo -e "  ${GREEN}✅ Status: $status_code (Expected: $expected_status)${NC}"
        return 0
    else
        echo -e "  ${RED}❌ Status: $status_code (Expected: $expected_status)${NC}"
        return 1
    fi
}

# Check if server is running
echo -e "${BLUE}🌐 Checking server connectivity...${NC}"
if curl -s "$API_BASE/ping" > /dev/null 2>&1; then
    echo -e "${GREEN}✅ Server is running${NC}"
else
    echo -e "${RED}❌ Server is not running. Please start with: php artisan serve${NC}"
    exit 1
fi

echo -e "\n${BLUE}📋 Testing New Endpoints (Without Authentication)${NC}"
echo "=================================================="

# Test endpoints that should return 401 without auth
failed_tests=0
total_tests=0

# Voucher endpoints
total_tests=$((total_tests + 1))
if test_endpoint "GET" "/vouchers/available" "Get Available Vouchers" "401" "auth"; then
    :
else
    failed_tests=$((failed_tests + 1))
fi

total_tests=$((total_tests + 1))
if test_endpoint "POST" "/vouchers/validate" "Validate Voucher" "401" "auth"; then
    :
else
    failed_tests=$((failed_tests + 1))
fi

# Wallet endpoints
total_tests=$((total_tests + 1))
if test_endpoint "GET" "/wallets/my/validation" "My Wallet Validation" "401" "auth"; then
    :
else
    failed_tests=$((failed_tests + 1))
fi

total_tests=$((total_tests + 1))
if test_endpoint "GET" "/wallets/threshold" "Wallet Threshold" "401" "auth"; then
    :
else
    failed_tests=$((failed_tests + 1))
fi

# Admin endpoints
total_tests=$((total_tests + 1))
if test_endpoint "GET" "/admin/wallet-settings/threshold" "Admin Get Threshold" "401" "auth"; then
    :
else
    failed_tests=$((failed_tests + 1))
fi

total_tests=$((total_tests + 1))
if test_endpoint "PUT" "/admin/wallet-settings/threshold" "Admin Update Threshold" "401" "auth"; then
    :
else
    failed_tests=$((failed_tests + 1))
fi

echo -e "\n${BLUE}📋 Testing Existing Endpoints (Sanity Check)${NC}"
echo "============================================="

# Test some existing endpoints to make sure we didn't break anything
total_tests=$((total_tests + 1))
if test_endpoint "GET" "/ping" "Health Check" "200" ""; then
    :
else
    failed_tests=$((failed_tests + 1))
fi

total_tests=$((total_tests + 1))
if test_endpoint "GET" "/services" "Get Services" "200" ""; then
    :
else
    failed_tests=$((failed_tests + 1))
fi

total_tests=$((total_tests + 1))
if test_endpoint "POST" "/auth/register" "User Registration" "422" ""; then
    :
else
    failed_tests=$((failed_tests + 1))
fi

# Summary
echo -e "\n${BLUE}📊 Endpoint Verification Summary${NC}"
echo "================================="
echo -e "Total endpoints tested: $total_tests"
echo -e "Responding correctly: $((total_tests - failed_tests))"
echo -e "Failed/Unexpected: $failed_tests"

if [ $failed_tests -eq 0 ]; then
    echo -e "\n${GREEN}🎉 All endpoints are responding correctly!${NC}"
    echo -e "${GREEN}✅ No routing or basic connectivity errors found${NC}"
    echo -e "\n${BLUE}📋 Verified Endpoints:${NC}"
    echo -e "  ✅ GET  /vouchers/available (401 - Auth required)"
    echo -e "  ✅ POST /vouchers/validate (401 - Auth required)"
    echo -e "  ✅ GET  /wallets/my/validation (401 - Auth required)"
    echo -e "  ✅ GET  /wallets/threshold (401 - Auth required)"
    echo -e "  ✅ GET  /admin/wallet-settings/threshold (401 - Auth required)"
    echo -e "  ✅ PUT  /admin/wallet-settings/threshold (401 - Auth required)"
    echo -e "  ✅ GET  /ping (200 - Public endpoint)"
    echo -e "  ✅ GET  /services (200 - Public endpoint)"
    echo -e "  ✅ POST /auth/register (422 - Validation required)"
    echo -e "\n${YELLOW}Next Step: Run full Playwright tests with authentication${NC}"
    echo -e "Command: ./test-integrated-features-playwright.sh"
    exit 0
else
    echo -e "\n${RED}❌ Some endpoints are not responding as expected${NC}"
    echo -e "\n${YELLOW}🔧 Possible Issues:${NC}"
    echo -e "1. Routes not properly registered in routes/api.php"
    echo -e "2. Controllers not found or have syntax errors"
    echo -e "3. Middleware not properly configured"
    echo -e "4. Services not properly imported"
    echo -e "\n${YELLOW}🔍 Debug Steps:${NC}"
    echo -e "1. Check Laravel logs: tail -f storage/logs/laravel.log"
    echo -e "2. Run: php artisan route:list | grep -E '(voucher|wallet)'"
    echo -e "3. Test individual endpoints manually with curl"
    exit 1
fi