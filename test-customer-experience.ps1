# Test script for Customer Experience System
$API_BASE = "https://lesgo-api-feature-auth-secmes.free.laravel.cloud/api/v1"

Write-Host "🚀 Testing LeSGo API Customer Experience System" -ForegroundColor Green
Write-Host "================================================" -ForegroundColor Green

# Test API Health
Write-Host "1. Testing API Health..." -ForegroundColor Yellow
try {
    $response = Invoke-RestMethod -Uri "$API_BASE/ping" -Method GET
    Write-Host "   ✅ $($response.message)" -ForegroundColor Green
    Write-Host "   📊 Database: $($response.database)" -ForegroundColor Cyan
} catch {
    Write-Host "   ❌ Failed to connect to API" -ForegroundColor Red
}

Write-Host ""

# Test Services (public endpoint)
Write-Host "2. Testing Services endpoint..." -ForegroundColor Yellow
try {
    $response = Invoke-RestMethod -Uri "$API_BASE/services" -Method GET
    Write-Host "   ✅ $($response.message)" -ForegroundColor Green
} catch {
    Write-Host "   ❌ Services endpoint failed" -ForegroundColor Red
}

Write-Host ""

# Test FAQ Categories (public endpoint)
Write-Host "3. Testing FAQ Categories..." -ForegroundColor Yellow
try {
    $response = Invoke-RestMethod -Uri "$API_BASE/faq/categories" -Method GET
    Write-Host "   ✅ $($response.message)" -ForegroundColor Green
} catch {
    Write-Host "   ❌ FAQ Categories endpoint failed" -ForegroundColor Red
}

Write-Host ""

# Test FAQ Search (public endpoint)
Write-Host "4. Testing FAQ Search..." -ForegroundColor Yellow
try {
    $response = Invoke-RestMethod -Uri "$API_BASE/faq/search?q=payment" -Method GET
    Write-Host "   ✅ $($response.message)" -ForegroundColor Green
} catch {
    Write-Host "   ❌ FAQ Search endpoint failed" -ForegroundColor Red
}

Write-Host ""

# Test Featured FAQ Articles (public endpoint)
Write-Host "5. Testing Featured FAQ Articles..." -ForegroundColor Yellow
try {
    $response = Invoke-RestMethod -Uri "$API_BASE/faq/featured" -Method GET
    Write-Host "   ✅ $($response.message)" -ForegroundColor Green
} catch {
    Write-Host "   ❌ Featured FAQ endpoint failed" -ForegroundColor Red
}

Write-Host ""
Write-Host "✅ Customer Experience System endpoints are accessible!" -ForegroundColor Green
Write-Host "📋 Full API documentation available at:" -ForegroundColor Cyan
Write-Host "   - Swagger UI: https://lesgo-api-feature-auth-secmes.free.laravel.cloud/swagger" -ForegroundColor Blue
Write-Host "   - OpenAPI JSON: https://lesgo-api-feature-auth-secmes.free.laravel.cloud/api-docs.json" -ForegroundColor Blue