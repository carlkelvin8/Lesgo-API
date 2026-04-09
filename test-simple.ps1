$API_BASE = "https://lesgo-api-feature-auth-secmes.free.laravel.cloud/api/v1"

Write-Host "Testing LeSGo API Customer Experience System" -ForegroundColor Green
Write-Host "=============================================" -ForegroundColor Green

Write-Host "1. API Health Check..." -ForegroundColor Yellow
$response = Invoke-RestMethod -Uri "$API_BASE/ping" -Method GET
Write-Host "   Status: $($response.message)" -ForegroundColor Green
Write-Host "   Database: $($response.database)" -ForegroundColor Cyan

Write-Host "2. Services Endpoint..." -ForegroundColor Yellow
$response = Invoke-RestMethod -Uri "$API_BASE/services" -Method GET
Write-Host "   Status: $($response.message)" -ForegroundColor Green

Write-Host "3. FAQ Categories..." -ForegroundColor Yellow
$response = Invoke-RestMethod -Uri "$API_BASE/faq/categories" -Method GET
Write-Host "   Status: $($response.message)" -ForegroundColor Green

Write-Host ""
Write-Host "Customer Experience System is working!" -ForegroundColor Green
Write-Host "Swagger UI: https://lesgo-api-feature-auth-secmes.free.laravel.cloud/swagger" -ForegroundColor Blue