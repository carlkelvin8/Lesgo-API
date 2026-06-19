# Clear Orders Data - Production Database (PowerShell)
# WARNING: This script deletes order data from production
# Use only during testing phase

Write-Host ""
Write-Host "════════════════════════════════════════════════════════════════" -ForegroundColor Cyan
Write-Host "  CLEAR ORDERS DATA - PRODUCTION DATABASE" -ForegroundColor Cyan
Write-Host "════════════════════════════════════════════════════════════════" -ForegroundColor Cyan
Write-Host ""

# Change to script directory
Set-Location -Path $PSScriptRoot

# Check if PHP is available
try {
    $phpVersion = php -v 2>$null
    if ($LASTEXITCODE -ne 0) {
        throw "PHP not found"
    }
} catch {
    Write-Host "✗ ERROR: PHP is not installed or not in PATH" -ForegroundColor Red
    Write-Host ""
    Write-Host "Please install PHP first:" -ForegroundColor Yellow
    Write-Host "  1. Download from: https://windows.php.net/download/" -ForegroundColor Yellow
    Write-Host "  2. Add PHP to PATH" -ForegroundColor Yellow
    Write-Host ""
    exit 1
}

# Run the PHP script
Write-Host "Executing PHP script..." -ForegroundColor Yellow
Write-Host ""

php clear_orders_production.php

if ($LASTEXITCODE -eq 0) {
    Write-Host ""
    Write-Host "✓ Script completed successfully!" -ForegroundColor Green
} else {
    Write-Host ""
    Write-Host "✗ Script failed with exit code: $LASTEXITCODE" -ForegroundColor Red
}

Write-Host ""
Write-Host "Press any key to exit..."
$null = $Host.UI.RawUI.ReadKey("NoEcho,IncludeKeyDown")
