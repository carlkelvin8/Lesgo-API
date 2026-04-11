<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="auth-token" content="{{ auth()->user()->createToken('wallet-validation')->plainTextToken ?? '' }}">
    <title>Driver Dashboard - Wallet Integration Example</title>
    
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <!-- Bootstrap for styling (optional) -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="driver-page" data-user-role="driver">
    <div class="container mt-4">
        <div class="row">
            <div class="col-md-8">
                <h2>Available Bookings</h2>
                
                <!-- Example booking card -->
                <div class="card mb-3">
                    <div class="card-body">
                        <h5 class="card-title">Order #12345</h5>
                        <p class="card-text">
                            <strong>From:</strong> Makati City<br>
                            <strong>To:</strong> BGC, Taguig<br>
                            <strong>Fare:</strong> ₱250.00
                        </p>
                        <button class="btn btn-success accept-booking-btn" data-order-id="12345">
                            Accept Booking
                        </button>
                    </div>
                </div>

                <!-- Another example booking -->
                <div class="card mb-3">
                    <div class="card-body">
                        <h5 class="card-title">Order #12346</h5>
                        <p class="card-text">
                            <strong>From:</strong> Quezon City<br>
                            <strong>To:</strong> Manila<br>
                            <strong>Fare:</strong> ₱180.00
                        </p>
                        <button class="btn btn-success" data-action="accept-booking" data-order-id="12346">
                            Accept Booking
                        </button>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header">
                        <h5>Driver Settings</h5>
                    </div>
                    <div class="card-body">
                        <!-- Wallet Balance Display -->
                        <div class="mb-3">
                            <label class="form-label">Wallet Balance</label>
                            <div class="input-group">
                                <span class="input-group-text">₱</span>
                                <input type="text" class="form-control" id="wallet-balance" readonly value="Loading...">
                                <button class="btn btn-outline-primary" type="button" onclick="window.location.href='/driver/wallet/topup'">
                                    Top Up
                                </button>
                            </div>
                            <small class="text-muted">Minimum required: ₱<span id="minimum-threshold">100.00</span></small>
                        </div>

                        <!-- Auto Assignment Toggle -->
                        <div class="mb-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="auto-assign-toggle">
                                <label class="form-check-label" for="auto-assign-toggle">
                                    Auto-accept bookings
                                </label>
                            </div>
                            <small class="text-muted">Automatically accept suitable bookings</small>
                        </div>

                        <!-- Wallet Status Indicator -->
                        <div class="alert alert-info" id="wallet-status" style="display: none;">
                            <small id="wallet-status-text"></small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Wallet Validation Script -->
    <script src="{{ asset('js/wallet-validation.js') }}"></script>
    
    <!-- Additional custom script for this page -->
    <script>
        document.addEventListener('DOMContentLoaded', async function() {
            // Update wallet balance display
            try {
                const response = await fetch('/api/v1/wallets/my/validation', {
                    headers: {
                        'Authorization': `Bearer ${document.querySelector('meta[name="auth-token"]').content}`,
                        'Accept': 'application/json'
                    }
                });
                
                if (response.ok) {
                    const data = await response.json();
                    const validation = data.data;
                    
                    // Update balance display
                    document.getElementById('wallet-balance').value = validation.current_balance.toFixed(2);
                    document.getElementById('minimum-threshold').textContent = validation.minimum_threshold.toFixed(2);
                    
                    // Update status indicator
                    const statusDiv = document.getElementById('wallet-status');
                    const statusText = document.getElementById('wallet-status-text');
                    
                    if (validation.has_sufficient_balance) {
                        statusDiv.className = 'alert alert-success';
                        statusText.textContent = '✅ Wallet balance is sufficient for receiving bookings';
                    } else {
                        statusDiv.className = 'alert alert-warning';
                        statusText.textContent = `⚠️ Need ₱${validation.shortfall.toFixed(2)} more to receive bookings`;
                    }
                    
                    statusDiv.style.display = 'block';
                }
            } catch (error) {
                console.error('Failed to load wallet balance:', error);
                document.getElementById('wallet-balance').value = 'Error loading';
            }
        });
    </script>
</body>
</html>