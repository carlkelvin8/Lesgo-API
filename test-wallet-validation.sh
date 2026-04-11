#!/bin/bash

# Wallet Balance Validation Test Runner
# This script runs comprehensive tests for the wallet validation feature

set -e

echo "🧪 Wallet Balance Validation Test Suite"
echo "========================================"

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

# Check if dependencies are installed
if [ ! -d "playwright/node_modules" ]; then
    echo -e "${YELLOW}📦 Installing Playwright dependencies...${NC}"
    cd playwright
    npm install
    cd ..
fi

# Function to run a specific test project
run_test() {
    local project_name=$1
    local description=$2
    
    echo -e "\n${BLUE}🔍 Running $description...${NC}"
    
    cd playwright
    if npx playwright test --project="$project_name" --reporter=list; then
        echo -e "${GREEN}✅ $description passed${NC}"
        return 0
    else
        echo -e "${RED}❌ $description failed${NC}"
        return 1
    fi
    cd ..
}

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

# Function to run database setup if needed
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

# Main test execution
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
    
    echo -e "\n${BLUE}🧪 Running Wallet Validation Tests...${NC}"
    echo "================================================"
    
    # Test 1: Backend API Tests
    total_tests=$((total_tests + 1))
    if ! run_test "wallet-validation" "Backend Wallet Validation API Tests"; then
        failed_tests=$((failed_tests + 1))
    fi
    
    # Test 2: Frontend JavaScript Tests
    total_tests=$((total_tests + 1))
    if ! run_test "wallet-validation-frontend" "Frontend Wallet Validation Tests"; then
        failed_tests=$((failed_tests + 1))
    fi
    
    # Test 3: Existing Wallet Tests (to ensure no regression)
    total_tests=$((total_tests + 1))
    if ! run_test "wallets" "Existing Wallet API Tests"; then
        failed_tests=$((failed_tests + 1))
    fi
    
    # Summary
    echo -e "\n${BLUE}📊 Test Summary${NC}"
    echo "==============="
    echo -e "Total tests: $total_tests"
    echo -e "Passed: $((total_tests - failed_tests))"
    echo -e "Failed: $failed_tests"
    
    if [ $failed_tests -eq 0 ]; then
        echo -e "\n${GREEN}🎉 All wallet validation tests passed!${NC}"
        echo -e "${GREEN}✅ Wallet balance validation feature is working correctly${NC}"
        exit 0
    else
        echo -e "\n${RED}❌ Some tests failed. Please check the output above.${NC}"
        exit 1
    fi
}

# Handle script arguments
case "${1:-}" in
    "backend")
        check_server && setup_database
        cd playwright
        npx playwright test --project="wallet-validation" --reporter=list
        ;;
    "frontend")
        check_server
        cd playwright
        npx playwright test --project="wallet-validation-frontend" --reporter=list
        ;;
    "wallets")
        check_server
        cd playwright
        npx playwright test --project="wallets" --reporter=list
        ;;
    "help"|"-h"|"--help")
        echo "Usage: $0 [backend|frontend|wallets|help]"
        echo ""
        echo "Options:"
        echo "  backend   - Run only backend API tests"
        echo "  frontend  - Run only frontend JavaScript tests"
        echo "  wallets   - Run existing wallet tests"
        echo "  help      - Show this help message"
        echo ""
        echo "Run without arguments to execute all wallet validation tests."
        ;;
    *)
        main
        ;;
esac