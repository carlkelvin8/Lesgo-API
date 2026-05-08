# Test Rate Limiting and Caching Implementation
# Run: powershell -ExecutionPolicy Bypass -File test_rate_limit_and_cache.ps1

Write-Host "=========================================" -ForegroundColor Cyan
Write-Host "Testing Rate Limiting & Caching" -ForegroundColor Cyan
Write-Host "=========================================" -ForegroundColor Cyan
Write-Host ""

$baseUrl = "http://localhost:8000/api/v1"

Write-Host "1. Testing Rate Limiting (Login Endpoint)" -ForegroundColor Yellow
Write-Host "-----------------------------------------" -ForegroundColor Yellow
Write-Host "Sending 7 login requests (limit is 5 per minute)..."
Write-Host ""

for ($i = 1; $i -le 7; $i++) {
    try {
        $body = @{
            email = "test@example.com"
            password = "wrong"
        } | ConvertTo-Json

        $response = Invoke-WebRequest -Uri "$baseUrl/auth/login" `
            -Method POST `
            -ContentType "application/json" `
            -Body $body `
            -ErrorAction SilentlyContinue

        $statusCode = $response.StatusCode
        
        if ($statusCode -eq 401 -or $statusCode -eq 422) {
            Write-Host "Request $i: $statusCode (Rate limit not hit yet) ✓" -ForegroundColor Green
        } else {
            Write-Host "Request $i: $statusCode" -ForegroundColor Yellow
        }
    }
    catch {
        $statusCode = $_.Exception.Response.StatusCode.value__
        
        if ($statusCode -eq 429) {
            Write-Host "Request $i: 429 Too Many Requests ✓" -ForegroundColor Red
            $errorBody = $_.ErrorDetails.Message
            Write-Host "Response: $errorBody" -ForegroundColor Gray
        } else {
            Write-Host "Request $i: $statusCode" -ForegroundColor Yellow
        }
    }
    
    Start-Sleep -Milliseconds 500
}

Write-Host ""
Write-Host "2. Testing Caching (Partner Menu Endpoint)" -ForegroundColor Yellow
Write-Host "-----------------------------------------" -ForegroundColor Yellow
Write-Host "Note: You need a valid token and partner ID for this test" -ForegroundColor Gray
Write-Host ""

# Uncomment and modify if you have a valid token
# $token = "your-token-here"
# $partnerId = 1
# 
# Write-Host "First request (should be cache MISS):"
# $headers = @{
#     "Authorization" = "Bearer $token"
# }
# $response = Invoke-WebRequest -Uri "$baseUrl/partners/$partnerId/menu" -Headers $headers
# Write-Host "X-Cache-Status: $($response.Headers['X-Cache-Status'])"
# 
# Write-Host ""
# Write-Host "Second request (should be cache HIT):"
# $response = Invoke-WebRequest -Uri "$baseUrl/partners/$partnerId/menu" -Headers $headers
# Write-Host "X-Cache-Status: $($response.Headers['X-Cache-Status'])"

Write-Host ""
Write-Host "=========================================" -ForegroundColor Cyan
Write-Host "Test Complete!" -ForegroundColor Cyan
Write-Host "=========================================" -ForegroundColor Cyan
Write-Host ""
Write-Host "Summary:" -ForegroundColor Yellow
Write-Host "- Rate limiting: Requests 1-5 should succeed (or 401/422)" -ForegroundColor White
Write-Host "- Rate limiting: Requests 6-7 should return 429" -ForegroundColor White
Write-Host "- Caching: First request = MISS, Second = HIT" -ForegroundColor White
Write-Host ""
Write-Host "Check the full documentation in:" -ForegroundColor Yellow
Write-Host "RATE_LIMITING_AND_CACHING_IMPLEMENTATION.md" -ForegroundColor White
