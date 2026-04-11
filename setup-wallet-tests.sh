#!/bin/bash

# Wallet Validation Test Setup Script
# This script sets up the testing environment for wallet validation

set -e

echo "🔧 Setting up Wallet Validation Tests"
echo "====================================="

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

echo -e "${BLUE}📦 Installing Playwright dependencies...${NC}"
cd playwright

# Install npm dependencies
if npm install; then
    echo -e "${GREEN}✅ Playwright dependencies installed${NC}"
else
    echo -e "${RED}❌ Failed to install Playwright dependencies${NC}"
    exit 1
fi

# Install Playwright browsers
echo -e "${BLUE}🌐 Installing Playwright browsers...${NC}"
if npx playwright install; then
    echo -e "${GREEN}✅ Playwright browsers installed${NC}"
else
    echo -e "${YELLOW}⚠️  Browser installation failed, but tests may still work${NC}"
fi

cd ..

# Check if Laravel dependencies are installed
echo -e "${BLUE}🔍 Checking Laravel setup...${NC}"
if [ ! -d "vendor" ]; then
    echo -e "${YELLOW}⚠️  Laravel vendor directory not found${NC}"
    echo -e "${BLUE}📦 Installing Composer dependencies...${NC}"
    
    if command -v composer &> /dev/null; then
        composer install
        echo -e "${GREEN}✅ Composer dependencies installed${NC}"
    else
        echo -e "${RED}❌ Composer not found. Please install Composer and run 'composer install'${NC}"
        exit 1
    fi
fi

# Check database setup
echo -e "${BLUE}🗄️  Checking database setup...${NC}"
if [ -f ".env" ]; then
    echo -e "${GREEN}✅ Environment file found${NC}"
else
    echo -e "${YELLOW}⚠️  .env file not found. Copying from .env.example...${NC}"
    if [ -f ".env.example" ]; then
        cp .env.example .env
        echo -e "${GREEN}✅ .env file created${NC}"
        echo -e "${YELLOW}⚠️  Please configure your database settings in .env${NC}"
    else
        echo -e "${RED}❌ .env.example not found${NC}"
    fi
fi

# Generate application key if needed
if ! grep -q "APP_KEY=" .env || grep -q "APP_KEY=$" .env; then
    echo -e "${BLUE}🔑 Generating application key...${NC}"
    php artisan key:generate
    echo -e "${GREEN}✅ Application key generated${NC}"
fi

# Run database migrations
echo -e "${BLUE}🗄️  Running database migrations...${NC}"
if php artisan migrate --force; then
    echo -e "${GREEN}✅ Database migrations completed${NC}"
else
    echo -e "${YELLOW}⚠️  Database migrations failed. Please check your database configuration${NC}"
fi

# Seed wallet threshold setting
echo -e "${BLUE}🌱 Seeding wallet threshold setting...${NC}"
if php artisan db:seed --class=WalletThresholdSeeder --force; then
    echo -e "${GREEN}✅ Wallet threshold setting seeded${NC}"
else
    echo -e "${YELLOW}⚠️  Wallet threshold seeding failed or already exists${NC}"
fi

# Test server connectivity
echo -e "${BLUE}🌐 Testing server connectivity...${NC}"
if php artisan serve --host=127.0.0.1 --port=8000 &
then
    SERVER_PID=$!
    echo "Server PID: $SERVER_PID"
    
    # Wait for server to start
    sleep 5
    
    if curl -s http://127.0.0.1:8000/api/v1/ping > /dev/null 2>&1; then
        echo -e "${GREEN}✅ Laravel server is working${NC}"
        kill $SERVER_PID 2>/dev/null || true
    else
        echo -e "${YELLOW}⚠️  Server test failed, but setup is complete${NC}"
        kill $SERVER_PID 2>/dev/null || true
    fi
else
    echo -e "${YELLOW}⚠️  Could not start test server${NC}"
fi

echo -e "\n${GREEN}🎉 Wallet validation test setup complete!${NC}"
echo -e "\n${BLUE}Next steps:${NC}"
echo -e "1. Start Laravel server: ${YELLOW}php artisan serve --host=127.0.0.1 --port=8000${NC}"
echo -e "2. Run wallet tests: ${YELLOW}./test-wallet-validation.sh${NC}"
echo -e "3. Or run specific tests:"
echo -e "   - Backend API: ${YELLOW}./test-wallet-validation.sh backend${NC}"
echo -e "   - Frontend JS: ${YELLOW}./test-wallet-validation.sh frontend${NC}"

echo -e "\n${BLUE}Test files created:${NC}"
echo -e "- playwright/tests/wallet-validation.spec.ts"
echo -e "- playwright/tests/wallet-validation-frontend.spec.ts"
echo -e "- test-wallet-validation.sh"
echo -e "- WALLET_VALIDATION_TESTING.md"