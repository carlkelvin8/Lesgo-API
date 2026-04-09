#!/bin/bash

echo "==> Deploying to Laravel Cloud"

# Install Laravel Cloud CLI if not installed
if ! command -v laravel-cloud &> /dev/null; then
    echo "Installing Laravel Cloud CLI..."
    composer global require laravel/cloud-cli
fi

# Login to Laravel Cloud (you'll need to do this manually)
echo "Please run: laravel-cloud login"
echo "Then run: laravel-cloud deploy"

# Or use the web interface:
echo ""
echo "==> Alternative: Use Laravel Cloud Web Interface"
echo "1. Go to https://cloud.laravel.com"
echo "2. Connect your GitHub repository"
echo "3. Select the 'railway-fix' branch"
echo "4. Laravel Cloud will auto-detect the configuration"
echo "5. Deploy!"

echo ""
echo "==> Configuration files created:"
echo "- .laravel-cloud.yml (Laravel Cloud specific)"
echo "- cloud.yml (Alternative format)"
echo ""
echo "Your Laravel Cloud deployment should work perfectly!"