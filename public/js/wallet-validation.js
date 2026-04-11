/**
 * Wallet Balance Validation for Riders
 * Requires: SweetAlert2, Axios (or fetch API)
 */

class WalletValidator {
    constructor(options = {}) {
        this.apiBaseUrl = options.apiBaseUrl || '/api/v1';
        this.authToken = options.authToken || null;
        this.enableAutoCheck = options.enableAutoCheck !== false;
        
        if (this.enableAutoCheck) {
            this.init();
        }
    }

    /**
     * Initialize wallet validation
     */
    init() {
        // Check wallet balance on page load for drivers
        if (this.isDriverPage()) {
            this.checkWalletBalance();
        }

        // Add event listeners for booking-related buttons
        this.attachEventListeners();
    }

    /**
     * Check if current page is for drivers
     */
    isDriverPage() {
        return window.location.pathname.includes('/driver') || 
               document.body.classList.contains('driver-page') ||
               document.querySelector('[data-user-role="driver"]');
    }

    /**
     * Attach event listeners to booking buttons
     */
    attachEventListeners() {
        // Accept booking buttons
        document.addEventListener('click', (e) => {
            if (e.target.matches('.accept-booking-btn, [data-action="accept-booking"]')) {
                e.preventDefault();
                this.handleBookingAcceptance(e.target);
            }
        });

        // Auto-assignment toggle (if exists)
        const autoAssignToggle = document.querySelector('#auto-assign-toggle, [data-action="toggle-auto-assign"]');
        if (autoAssignToggle) {
            autoAssignToggle.addEventListener('change', (e) => {
                if (e.target.checked) {
                    this.validateBeforeAutoAssign(e.target);
                }
            });
        }
    }

    /**
     * Check wallet balance via API
     */
    async checkWalletBalance() {
        try {
            const response = await this.apiCall('/wallets/my/validation');
            const validation = response.data;

            if (!validation.has_sufficient_balance) {
                this.showInsufficientBalanceWarning(validation);
                this.disableBookingFeatures();
            } else {
                this.enableBookingFeatures();
            }

            return validation;
        } catch (error) {
            console.error('Wallet validation error:', error);
            return null;
        }
    }

    /**
     * Handle booking acceptance with validation
     */
    async handleBookingAcceptance(button) {
        const orderId = button.dataset.orderId;
        if (!orderId) return;

        // Show loading state
        const originalText = button.textContent;
        button.textContent = 'Checking...';
        button.disabled = true;

        try {
            const validation = await this.checkWalletBalance();
            
            if (!validation || !validation.has_sufficient_balance) {
                this.showInsufficientBalanceAlert(validation);
                return;
            }

            // Proceed with booking acceptance
            await this.acceptBooking(orderId);
            
        } catch (error) {
            console.error('Booking acceptance error:', error);
            this.showErrorAlert('Failed to accept booking. Please try again.');
        } finally {
            button.textContent = originalText;
            button.disabled = false;
        }
    }

    /**
     * Validate before enabling auto-assignment
     */
    async validateBeforeAutoAssign(toggle) {
        const validation = await this.checkWalletBalance();
        
        if (!validation || !validation.has_sufficient_balance) {
            toggle.checked = false;
            this.showInsufficientBalanceAlert(validation, {
                title: 'Cannot Enable Auto-Assignment',
                text: 'You need sufficient wallet balance to receive bookings automatically.'
            });
        }
    }

    /**
     * Accept booking via API
     */
    async acceptBooking(orderId) {
        try {
            const response = await this.apiCall(`/orders/${orderId}/status`, {
                method: 'PATCH',
                body: JSON.stringify({ status: 'accepted' })
            });

            if (response.success) {
                Swal.fire({
                    icon: 'success',
                    title: 'Booking Accepted!',
                    text: 'You have successfully accepted this booking.',
                    timer: 2000,
                    showConfirmButton: false
                });
                
                // Refresh page or update UI
                setTimeout(() => window.location.reload(), 2000);
            }
        } catch (error) {
            throw error;
        }
    }

    /**
     * Show insufficient balance warning (non-blocking)
     */
    showInsufficientBalanceWarning(validation) {
        const toast = Swal.mixin({
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 5000,
            timerProgressBar: true
        });

        toast.fire({
            icon: 'warning',
            title: 'Low Wallet Balance',
            text: `Balance: ₱${validation.current_balance.toFixed(2)} (Required: ₱${validation.minimum_threshold.toFixed(2)})`
        });
    }

    /**
     * Show insufficient balance alert (blocking)
     */
    showInsufficientBalanceAlert(validation, options = {}) {
        const shortfall = validation ? validation.shortfall : 0;
        const currentBalance = validation ? validation.current_balance : 0;
        const threshold = validation ? validation.minimum_threshold : 100;

        Swal.fire({
            icon: 'warning',
            title: options.title || 'Insufficient Wallet Balance',
            html: `
                <div class="wallet-alert-content">
                    <p>${options.text || 'You need to top up your wallet before receiving bookings.'}</p>
                    <div class="balance-info">
                        <div class="balance-row">
                            <span>Current Balance:</span>
                            <span class="amount">₱${currentBalance.toFixed(2)}</span>
                        </div>
                        <div class="balance-row">
                            <span>Required Minimum:</span>
                            <span class="amount">₱${threshold.toFixed(2)}</span>
                        </div>
                        <div class="balance-row shortage">
                            <span>Amount Needed:</span>
                            <span class="amount">₱${shortfall.toFixed(2)}</span>
                        </div>
                    </div>
                </div>
            `,
            showCancelButton: true,
            confirmButtonText: 'Top Up Wallet',
            cancelButtonText: 'Later',
            confirmButtonColor: '#3085d6',
            customClass: {
                htmlContainer: 'wallet-alert-container'
            }
        }).then((result) => {
            if (result.isConfirmed) {
                this.redirectToTopUp();
            }
        });
    }

    /**
     * Show generic error alert
     */
    showErrorAlert(message) {
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: message
        });
    }

    /**
     * Disable booking-related features
     */
    disableBookingFeatures() {
        const bookingButtons = document.querySelectorAll('.accept-booking-btn, [data-action="accept-booking"]');
        bookingButtons.forEach(btn => {
            btn.disabled = true;
            btn.title = 'Insufficient wallet balance';
        });

        const autoAssignToggle = document.querySelector('#auto-assign-toggle, [data-action="toggle-auto-assign"]');
        if (autoAssignToggle) {
            autoAssignToggle.disabled = true;
            autoAssignToggle.checked = false;
        }
    }

    /**
     * Enable booking-related features
     */
    enableBookingFeatures() {
        const bookingButtons = document.querySelectorAll('.accept-booking-btn, [data-action="accept-booking"]');
        bookingButtons.forEach(btn => {
            btn.disabled = false;
            btn.title = '';
        });

        const autoAssignToggle = document.querySelector('#auto-assign-toggle, [data-action="toggle-auto-assign"]');
        if (autoAssignToggle) {
            autoAssignToggle.disabled = false;
        }
    }

    /**
     * Redirect to wallet top-up page
     */
    redirectToTopUp() {
        // Customize this URL based on your app structure
        const topUpUrl = '/driver/wallet/topup';
        window.location.href = topUpUrl;
    }

    /**
     * Make API calls with authentication
     */
    async apiCall(endpoint, options = {}) {
        const url = `${this.apiBaseUrl}${endpoint}`;
        const headers = {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            ...options.headers
        };

        if (this.authToken) {
            headers['Authorization'] = `Bearer ${this.authToken}`;
        }

        const response = await fetch(url, {
            ...options,
            headers
        });

        if (!response.ok) {
            throw new Error(`API call failed: ${response.status}`);
        }

        return await response.json();
    }
}

// Auto-initialize if SweetAlert2 is available
if (typeof Swal !== 'undefined') {
    document.addEventListener('DOMContentLoaded', () => {
        // Get auth token from meta tag or localStorage
        const authToken = document.querySelector('meta[name="auth-token"]')?.content || 
                         localStorage.getItem('auth_token');
        
        window.walletValidator = new WalletValidator({
            authToken: authToken
        });
    });
}

// CSS for wallet alert styling
const walletAlertStyles = `
<style>
.wallet-alert-container .balance-info {
    margin: 15px 0;
    padding: 15px;
    background: #f8f9fa;
    border-radius: 8px;
    text-align: left;
}

.wallet-alert-container .balance-row {
    display: flex;
    justify-content: space-between;
    margin: 8px 0;
    padding: 5px 0;
}

.wallet-alert-container .balance-row.shortage {
    border-top: 1px solid #dee2e6;
    margin-top: 10px;
    padding-top: 10px;
    font-weight: bold;
    color: #dc3545;
}

.wallet-alert-container .amount {
    font-weight: bold;
    color: #495057;
}

.wallet-alert-container .shortage .amount {
    color: #dc3545;
}
</style>
`;

// Inject styles
document.head.insertAdjacentHTML('beforeend', walletAlertStyles);