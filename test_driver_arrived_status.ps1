# Test script for Driver Arrived Status feature (PowerShell)
# This script tests the new driver_arrived_at_pickup status

Write-Host "=== Testing Driver Arrived Status Feature ===" -ForegroundColor Cyan
Write-Host ""

# Configuration
$API_URL = "http://localhost:8000/api/v1"
$ORDER_ID = if ($args[0]) { $args[0] } else { 1 }
$DRIVER_TOKEN = "YOUR_DRIVER_TOKEN_HERE"

Write-Host "Testing order ID: $ORDER_ID" -ForegroundColor Yellow
Write-Host ""

# Test 1: Get current order status
Write-Host "1. Getting current order status..." -ForegroundColor Green
$response1 = Invoke-RestMethod -Uri "$API_URL/orders/$ORDER_ID" `
    -Method Get `
    -Headers @{
        "Authorization" = "Bearer $DRIVER_TOKEN"
        "Content-Type" = "application/json"
    }

$response1.data | Select-Object id, status, accepted_at, driver_arrived_at_pickup_at, picked_up_at | Format-List

Write-Host ""

# Test 2: Update status to driver_arrived_at_pickup
Write-Host "2. Updating status to 'driver_arrived_at_pickup'..." -ForegroundColor Green
$body = @{
    status = "driver_arrived_at_pickup"
} | ConvertTo-Json

try {
    $response2 = Invoke-RestMethod -Uri "$API_URL/orders/$ORDER_ID/status" `
        -Method Patch `
        -Headers @{
            "Authorization" = "Bearer $DRIVER_TOKEN"
            "Content-Type" = "application/json"
        } `
        -Body $body

    Write-Host "Response:" -ForegroundColor Yellow
    $response2 | ConvertTo-Json -Depth 5
} catch {
    Write-Host "Error: $_" -ForegroundColor Red
    Write-Host $_.Exception.Response.StatusCode -ForegroundColor Red
}

Write-Host ""

# Test 3: Verify the status was updated
Write-Host "3. Verifying status update..." -ForegroundColor Green
$response3 = Invoke-RestMethod -Uri "$API_URL/orders/$ORDER_ID" `
    -Method Get `
    -Headers @{
        "Authorization" = "Bearer $DRIVER_TOKEN"
        "Content-Type" = "application/json"
    }

$response3.data | Select-Object id, status, accepted_at, driver_arrived_at_pickup_at, picked_up_at | Format-List

Write-Host ""
Write-Host "=== Test Complete ===" -ForegroundColor Cyan
Write-Host ""
Write-Host "Expected results:" -ForegroundColor Yellow
Write-Host "- Status should be 'driver_arrived_at_pickup'"
Write-Host "- driver_arrived_at_pickup_at should have a timestamp"
Write-Host "- Customer should receive a real-time notification"
