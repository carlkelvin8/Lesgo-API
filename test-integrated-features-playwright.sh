#!/bin/bash

# Comprehensive Playwright Test Runner for Integrated Features
# Tests all new endpoints and features for errors

set -e

echo "🧪 Testing Integrated Features with Playwright"
echo "=============================================="

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Check if we're in the right directory
if [ ! -f "playwright/playwright.config.ts" ]; then
    echo -e "${RED}❌ Error: Please run this script from the project root directory${NC}"
    exit 1
fi

# Function to check if Laravel server is running
check_server() {
    echo -e "${BLUE}🌐 Checking Laravel server...${NC}"
    
    if curl -s http://127.0.0.1:8000/api/v1/ping > /dev/null 2>&1; then
        echo -e "${GREEN}✅ Laravel server is running${NC}"
        return 0
    else
        echo -e "${YELLOW}⚠️  Laravel server not detected. Starting server...${NC}"
        
        # Check if we can start the server
        if [ -f "artisan" ]; then
            echo -e "${BLUE}🚀 Starting Laravel development server...${NC}"
            php artisan serve --host=127.0.0.1 --port=8000 &
            SERVER_PID=$!
            
            # Wait for server to start
            echo "Waiting for server to start..."
            sleep 5
            
            if curl -s http://127.0.0.1:8000/api/v1/ping > /dev/null 2>&1; then
                echo -e "${GREEN}✅ Laravel server started successfully${NC}"
                return 0
            else
                echo -e "${RED}❌ Failed to start Laravel server${NC}"
                kill $SERVER_PID 2>/dev/null || true
                return 1
            fi
        else
            echo -e "${RED}❌ Laravel artisan not found. Please start the server manually:${NC}"
            echo "php artisan serve --host=127.0.0.1 --port=8000"
            return 1
        fi
    fi
}

# Function to setup database
setup_database() {
    echo -e "${BLUE}🗄️  Setting up database for testing...${NC}"
    
    if [ -f "artisan" ]; then
        # Run migrations (if needed)
        if php artisan migrate:status > /dev/null 2>&1; then
            echo -e "${GREEN}✅ Database is ready${NC}"
        else
            echo -e "${YELLOW}⚠️  Running database migrations...${NC}"
            php artisan migrate --force
        fi
        
        # Seed wallet threshold setting
        echo -e "${BLUE}🌱 Seeding wallet threshold setting...${NC}"
        php artisan db:seed --class=WalletThresholdSeeder --force 2>/dev/null || echo -e "${YELLOW}⚠️  Seeder may have already run${NC}"
    else
        echo -e "${YELLOW}⚠️  Skipping database setup (artisan not found)${NC}"
    fi
}

# Function to install Playwright dependencies
setup_playwright() {
    echo -e "${BLUE}📦 Setting up Playwright...${NC}"
    
    cd playwright
    
    # Install npm dependencies if needed
    if [ ! -d "node_modules" ]; then
        echo -e "${BLUE}📦 Installing Playwright dependencies...${NC}"
        npm install
    fi
    
    # Install browsers if needed
    if ! npx playwright --version > /dev/null 2>&1; then
        echo -e "${BLUE}🌐 Installing Playwright browsers...${NC}"
        npx playwright install
    fi
    
    cd ..
    echo -e "${GREEN}✅ Playwright setup complete${NC}"
}

# Function to run specific test
run_test() {
    local test_name=$1
    local description=$2
    
    echo -e "\n${BLUE}🔍 Running $description...${NC}"
    
    cd playwright
    if npx playwright test --project="$test_name" --reporter=list; then
        echo -e "${GREEN}✅ $description passed${NC}"
        cd ..
        return 0
    else
        echo -e "${RED}❌ $description failed${NC}"
        cd ..
        return 1
    fi
}

# Function to run comprehensive test
run_comprehensive_test() {
    echo -e "\n${BLUE}🧪 Running Comprehensive Integrated Features Test...${NC}"
    echo "================================================================"
    
    cd playwright
    
    # Run with detailed output and HTML report
    if npx playwright test --project="integrated-features" --reporter=html --reporter=list; then
        echo -e "${GREEN}✅ All integrated features tests passed!${NC}"
        
        # Generate and show report
        echo -e "\n${BLUE}📊 Generating test report...${NC}"
        npx playwright show-report --host=127.0.0.1 --port=9323 &
        REPORT_PID=$!
        
        echo -e "${GREEN}📊 Test report available at: http://127.0.0.1:9323${NC}"
        echo -e "${YELLOW}Press Ctrl+C to stop the report server${NC}"
        
        # Wait a bit then kill the report server
        sleep 3
        kill $REPORT_PID 2>/dev/null || true
        
        cd ..
        return 0
    else
        echo -e "${RED}❌ Some integrated features tests failed${NC}"
        
        # Still generate report for debugging
        echo -e "\n${BLUE}📊 Generating failure report...${NC}"
        npx playwright show-report --host=127.0.0.1 --port=9323 &
        REPORT_PID=$!
        
        echo -e "${YELLOW}📊 Failure report available at: http://127.0.0.1:9323${NC}"
        echo -e "${YELLOW}Press Ctrl+C to stop the report server${NC}"
        
        sleep 3
        kill $REPORT_PID 2>/dev/null || true
        
        cd ..
        return 1
    fi
}

# Main execution
main() {
    local failed_tests=0
    local total_tests=0
    
    echo -e "${BLUE}🔧 Pre-flight checks...${NC}"
    
    # Check server
    if ! check_server; then
        echo -e "${RED}❌ Cannot proceed without Laravel server${NC}"
        exit 1
    fi
    
    # Setup database
    setup_database
    
    # Setup Playwright
    setup_playwright
    
    echo -e "\n${BLUE}🧪 Testing All Integrated Features...${NC}"
    echo "================================================"
    
    # Test 1: Comprehensive Integrated Features Test
    total_tests=$((total_tests + 1))
    if ! run_comprehensive_test; then
        failed_tests=$((failed_tests + 1))
    fi
    
    # Test 2: Individual Feature Tests (if comprehensive fails)
    if [ $failed_tests -gt 0 ]; then
        echo -e "\n${YELLOW}🔍 Running individual feature tests for debugging...${NC}"
        
        # Test voucher system
        total_tests=$((total_tests + 1))
        echo -e "\n${BLUE}Testing Voucher System Endpoints...${NC}"
        cd playwright
        if npx playwright test --project="integrated-features" --grep="voucher" --reporter=list; then
            echo -e "${GREEN}✅ Voucher system tests passed${NC}"
        else
            echo -e "${RED}❌ Voucher system tests failed${NC}"
            failed_tests=$((failed_tests + 1))
        fi
        cd ..
        
        # Test wallet validation
        total_tests=$((total_tests + 1))
        echo -e "\n${BLUE}Testing Wallet Validation Endpoints...${NC}"
        cd playwright
        if npx playwright test --project="integrated-features" --grep="wallet" --reporter=list; then
            echo -e "${GREEN}✅ Wallet validation tests passed${NC}"
        else
            echo -e "${RED}❌ Wallet validation tests failed${NC}"
            failed_tests=$((failed_tests + 1))
        fi
        cd ..
        
        # Test enhanced tracking
        total_tests=$((total_tests + 1))
        echo -e "\n${BLUE}Testing Enhanced Tracking Endpoints...${NC}"
        cd playwright
        if npx playwright test --project="integrated-features" --grep="tracking" --reporter=list; then
            echo -e "${GREEN}✅ Enhanced tracking tests passed${NC}"
        else
            echo -e "${RED}❌ Enhanced tracking tests failed${NC}"
            failed_tests=$((failed_tests + 1))
        fi
        cd ..
    fi
    
    # Summary
    echo -e "\n${BLUE}📊 Test Summary${NC}"
    echo "==============="
    echo -e "Total test suites: $total_tests"
    echo -e "Passed: $((total_tests - failed_tests))"
    echo -e "Failed: $failed_tests"
    
    if [ $failed_tests -eq 0 ]; then
        echo -e "\n${GREEN}🎉 All integrated features tests passed!${NC}"
        echo -e "${GREEN}✅ No errors found in any endpoints${NC}"
        echo -e "\n${BLUE}📋 Tested Features:${NC}"
        echo -e "  ✅ Voucher System (GET /vouchers/available, POST /vouchers/validate)"
        echo -e "  ✅ Enhanced Order Creation (POST /orders with auto-assignment & vouchers)"
        echo -e "  ✅ Predictive ETA Tracking (GET /tracking/orders/{id})"
        echo -e "  ✅ Wallet Balance Validation (GET /wallets/my/validation)"
        echo -e "  ✅ Admin Wallet Settings (GET/PUT /admin/wallet-settings/threshold)"
        echo -e "  ✅ Driver Auto-Assignment (PATCH /orders/{id}/status)"
        echo -e "  ✅ Authentication & Authorization"
        echo -e "  ✅ Error Handling & Edge Cases"
        exit 0
    else
        echo -e "\n${RED}❌ Some tests failed. Check the output above for details.${NC}"
        echo -e "\n${YELLOW}🔧 Debugging Tips:${NC}"
        echo -e "1. Check Laravel logs: tail -f storage/logs/laravel.log"
        echo -e "2. Verify database migrations: php artisan migrate:status"
        echo -e "3. Check if all services are properly imported"
        echo -e "4. Run individual tests for more specific error messages"
        exit 1
    fi
}

# Handle script arguments
case "${1:-}" in
    "vouchers")
        check_server && setup_database && setup_playwright
        cd playwright
        npx playwright test --project="integrated-features" --grep="voucher" --reporter=list
        ;;
    "tracking")
        check_server && setup_database && setup_playwright
        cd playwright
        npx playwright test --project="integrated-features" --grep="tracking" --reporter=list
        ;;
    "wallets")
        check_server && setup_database && setup_playwright
        cd playwright
        npx playwright test --project="integrated-features" --grep="wallet" --reporter=list
        ;;
    "orders")
        check_server && setup_database && setup_playwright
        cd playwright
        npx playwright test --project="integrated-features" --grep="orders" --reporter=list
        ;;
    "help"|"-h"|"--help")
        echo "Usage: $0 [vouchers|tracking|wallets|orders|help]"
        echo ""
        echo "Options:"
        echo "  vouchers  - Test only voucher system endpoints"
        echo "  tracking  - Test only enhanced tracking endpoints"
        echo "  wallets   - Test only wallet validation endpoints"
        echo "  orders    - Test only order creation and assignment"
        echo "  help      - Show this help message"
        echo ""
        echo "Run without arguments to execute all integrated features tests."
        ;;
    *)
        main
        ;;
esac