<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Api\Auth\AuthController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\PartnerController;
use App\Http\Controllers\Api\PartnerBranchController;
use App\Http\Controllers\Api\AddressController;
use App\Http\Controllers\Api\DriverProfileController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\ServiceController;
use App\Http\Controllers\Api\WalletController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\PaymentGatewayController;
use App\Http\Controllers\Api\PaymentGatewayInvoicesController;
use App\Http\Controllers\Api\ReceiptController;
use App\Http\Controllers\Api\DistanceController;
use App\Http\Controllers\Api\ChecklistTemplateController;
use App\Http\Controllers\Api\HealthCheckController;
use App\Http\Controllers\Api\SecurityController;
use App\Http\Controllers\Api\GooglePlacesProxyController;
use App\Http\Controllers\Api\DeviceController;
use App\Http\Controllers\Api\FeedbackController;

Route::prefix('v1')->group(function () {

    /* =========================
       PUBLIC
    ========================= */

    // Storage files with CORS headers
    Route::get('/storage/{path}', function ($path) {
        $filePath = storage_path('app/public/' . $path);
        
        if (!file_exists($filePath)) {
            abort(404);
        }
        
        $mimeType = mime_content_type($filePath);
        $file = file_get_contents($filePath);
        
        return response($file, 200)
            ->header('Content-Type', $mimeType)
            ->header('Access-Control-Allow-Origin', '*')
            ->header('Access-Control-Allow-Methods', 'GET, OPTIONS')
            ->header('Access-Control-Allow-Headers', '*');
    })->where('path', '.*');

    // Health check endpoints
    Route::get('/ping', [HealthCheckController::class, 'ping']);
    Route::get('/health/liveness', [HealthCheckController::class, 'liveness']);
    Route::get('/health/readiness', [HealthCheckController::class, 'readiness']);

    // Legacy ping (keep for backward compatibility)
    Route::get('/google-places/autocomplete', [GooglePlacesProxyController::class, 'autocomplete']);
    Route::get('/google-places/details', [GooglePlacesProxyController::class, 'details']);
    Route::get('/google-places/directions', [GooglePlacesProxyController::class, 'directions']);
    Route::get('/health', HealthCheckController::class);
    Route::get('/config/mobile', \App\Http\Controllers\Api\MobileConfigController::class);

    // Legacy ping (keep for backward compatibility)
    Route::get('/ping-legacy', function () {
        $response = [
            'message' => 'LeSGo API v1 OK',
            'timestamp' => now()->toISOString(),
            'environment' => app()->environment(),
            'php_version' => PHP_VERSION,
            'laravel_version' => app()->version(),
            'server_port' => $_SERVER['SERVER_PORT'] ?? 'unknown',
            'request_uri' => $_SERVER['REQUEST_URI'] ?? 'unknown',
        ];

        // Only check database/redis if they're configured
        if (config('database.default') && config('database.connections.' . config('database.default'))) {
            try {
                \DB::connection()->getPdo();
                $response['database'] = 'connected';
            } catch (\Exception $e) {
                $response['database'] = 'not configured or error: ' . $e->getMessage();
            }
        } else {
            $response['database'] = 'not configured';
        }

        if (config('cache.default') === 'redis' && config('database.redis.default')) {
            try {
                \Redis::ping();
                $response['redis'] = 'connected';
            } catch (\Exception $e) {
                $response['redis'] = 'not configured or error: ' . $e->getMessage();
            }
        } else {
            $response['redis'] = 'not configured';
        }

        return response()->json($response);
    });

    // AUTH (public + protected) - Stricter rate limiting
    Route::prefix('auth')->middleware('throttle:auth')->group(function () {
        Route::post('/register', [AuthController::class, 'register']);
        Route::post('/login', [AuthController::class, 'login']);
        Route::post('/google', [\App\Http\Controllers\Api\Auth\GoogleAuthController::class, 'handleGoogleSignIn']);

        // Forgot Password endpoints (public, rate-limited)
        Route::post('/forgot-password/send-otp', [\App\Http\Controllers\Api\Auth\ForgotPasswordController::class, 'sendOtp'])
            ->middleware('throttle:3,5'); // 3 per 5 minutes
        Route::post('/forgot-password/verify-otp', [\App\Http\Controllers\Api\Auth\ForgotPasswordController::class, 'verifyOtp'])
            ->middleware('throttle:10,1'); // 10 per minute
        Route::post('/forgot-password/reset', [\App\Http\Controllers\Api\Auth\ForgotPasswordController::class, 'resetPassword'])
            ->middleware('throttle:5,1'); // 5 per minute
        Route::post('/forgot-password/resend-otp', [\App\Http\Controllers\Api\Auth\ForgotPasswordController::class, 'resendOtp'])
            ->middleware('throttle:2,10'); // 2 per 10 minutes

        // OTP endpoints (public, rate-limited)
        Route::post('/otp/send', [\App\Http\Controllers\Api\Auth\OtpController::class, 'send'])
            ->middleware('throttle:5,1'); // 5 per minute
        Route::post('/otp/verify', [\App\Http\Controllers\Api\Auth\OtpController::class, 'verify'])
            ->middleware('throttle:10,1'); // 10 per minute
        Route::post('/otp/cancel', [\App\Http\Controllers\Api\Auth\OtpController::class, 'cancel'])
            ->middleware('throttle:5,1'); // 5 per minute
        Route::post('/otp/check', [\App\Http\Controllers\Api\Auth\OtpController::class, 'checkVerification'])
            ->middleware('throttle:10,1'); // 10 per minute

        // Public refresh — accepts refresh_token in body (no Bearer required)
        Route::post('/refresh', [AuthController::class, 'refresh']);

        Route::middleware('auth:sanctum')->group(function () {
            Route::get('/me', [AuthController::class, 'me']);
            Route::put('/me', [AuthController::class, 'updateProfile']);
            Route::post('/me/profile-picture', [AuthController::class, 'uploadProfilePicture']);
            Route::post('/change-password', [AuthController::class, 'changePassword']);
            Route::post('/account/deactivate', [AuthController::class, 'deactivateAccount']);
            Route::post('/logout', [AuthController::class, 'logout']);
            Route::post('/logout-all', [AuthController::class, 'logoutAll']);
            Route::post('/fcm-token', [AuthController::class, 'registerFcmToken']);
        });
    });

    // Services (public) - General API rate limit
    Route::middleware('throttle:api')->group(function () {
        Route::get('/services', [ServiceController::class, 'index']);
        Route::get('/services/{service}', [ServiceController::class, 'show']);

        // Partners / restaurants list (public — shown on home screen)
        Route::get('/partners', [PartnerController::class, 'index']);
        Route::get('/partners/{partner}', [PartnerController::class, 'show']);
        Route::get('/partners/{partner}/menu', [PartnerController::class, 'menu']);
        Route::get('/partners/{partner}/reviews', [\App\Http\Controllers\Api\RatingReviewController::class, 'partnerReviews']);
        Route::get('/partners/{partner_id}/branches', [PartnerBranchController::class, 'index']);
    });

    // Driver registration (public) - Stricter rate limiting
    Route::post('/drivers/register', [DriverProfileController::class, 'register'])
        ->middleware('throttle:driver-registration');

    // Payment webhooks (public — verified by X-CALLBACK-TOKEN, no Bearer token)
    Route::post('/webhooks/payments/{provider}', [PaymentController::class, 'webhook'])
        ->middleware('throttle:60,1')
        ->whereIn('provider', ['xendit', 'gcash', 'maya']);

    // Public Social Media endpoints
    Route::prefix('social')->group(function () {
        Route::get('/shares/{share}/public', [\App\Http\Controllers\Api\SocialMediaController::class, 'publicShare']);
        Route::get('/trending', [\App\Http\Controllers\Api\SocialMediaController::class, 'trending']);
        Route::get('/statistics', [\App\Http\Controllers\Api\SocialMediaController::class, 'statistics']);
    });

    // Public proof images (served with CORS headers)
    Route::get('/proof-images/{orderId}/{filename}', [\App\Http\Controllers\Api\ProofImageController::class, 'show']);

    /* =========================
       PROTECTED (ALL BELOW REQUIRE Bearer token)
    ========================= */
    Route::middleware(['auth:sanctum', 'throttle:authenticated'])->group(function () {

        // Users
        Route::get('/users', [UserController::class, 'index']);
        Route::post('/users', [UserController::class, 'store']);
        Route::get('/users/{user}', [UserController::class, 'show']);
        Route::patch('/users/{user}', [UserController::class, 'update']);
        Route::delete('/users/{user}', [UserController::class, 'destroy']);

        // Partners (admin only - create/update)
        Route::post('/partners', [PartnerController::class, 'store']);
        Route::patch('/partners/{partner}', [PartnerController::class, 'update']);

        // Menu Items (partner can manage their own menu)
        Route::post('/partners/{partner}/menu-items', [PartnerController::class, 'storeMenuItem']);
        Route::patch('/menu-items/{menuItem}', [PartnerController::class, 'updateMenuItem']);
        Route::delete('/menu-items/{menuItem}', [PartnerController::class, 'deleteMenuItem']);

        // Menu Categories (partner can manage their own categories)
        Route::post('/partners/{partner}/menu-categories', [PartnerController::class, 'storeMenuCategory']);
        Route::patch('/menu-categories/{menuCategory}', [PartnerController::class, 'updateMenuCategory']);
        Route::delete('/menu-categories/{menuCategory}', [PartnerController::class, 'deleteMenuCategory']);

        // Partner branches
        Route::get('/partners/{partner_id}/branches', [PartnerBranchController::class, 'index']);
        Route::post('/partners/{partner_id}/branches', [PartnerBranchController::class, 'store']);
        Route::patch('/branches/{branch}', [PartnerBranchController::class, 'update']);
        Route::delete('/branches/{branch}', [PartnerBranchController::class, 'destroy']);

        // Addresses
        Route::get('/addresses', [AddressController::class, 'myIndex']); // Current user's addresses
        Route::post('/addresses', [AddressController::class, 'myStore']); // Create address for current user
        Route::get('/users/{user_id}/addresses', [AddressController::class, 'index']);
        Route::post('/users/{user_id}/addresses', [AddressController::class, 'store']);
        Route::patch('/addresses/{address}', [AddressController::class, 'update']);
        Route::delete('/addresses/{address}', [AddressController::class, 'destroy']);

        // Drivers (protected reads/updates)
        Route::get('/drivers', [DriverProfileController::class, 'index']);
        Route::get('/drivers/{driverProfile}', [DriverProfileController::class, 'show']);
        Route::patch('/drivers/{driverProfile}', [DriverProfileController::class, 'update']);
        Route::patch('/drivers/{driverProfile}/status', [DriverProfileController::class, 'updateStatus']);
        Route::patch('/drivers/{driverProfile}/location', [DriverProfileController::class, 'updateLocation']);

        // Orders
        Route::post('/orders/estimate', [\App\Http\Controllers\Api\OrderEstimateController::class, 'estimate']); // Step 1: get fare before booking
        Route::get('/orders', [OrderController::class, 'index']);
        Route::post('/orders', [OrderController::class, 'store']);                                                // Step 2: confirm and book
        Route::get('/orders/{order}', [OrderController::class, 'show']);
        Route::post('/orders/{order}/reorder', [OrderController::class, 'reorder']);
        Route::patch('/orders/{order}/status', [OrderController::class, 'updateStatus']);
        Route::post('/orders/{order}/upload-proof', [OrderController::class, 'uploadProof']);
        Route::get('/orders/{order}/receipt', [ReceiptController::class, 'show']);
        Route::get('/orders/{order}/tracking', [\App\Http\Controllers\Api\OrderTrackingController::class, 'trackOrder']);
        Route::get('/orders/{order}/driver-location', [\App\Http\Controllers\Api\OrderTrackingController::class, 'liveLocation']);

        // Lesbuy Checklist
        Route::get('/checklist-templates', [ChecklistTemplateController::class, 'index']);
        Route::post('/checklist-templates', [ChecklistTemplateController::class, 'store']);

        // Distance
        Route::get('/distance/calculate', [DistanceController::class, 'calculate']);
        Route::post('/distance/calculate', [DistanceController::class, 'calculatePost']);
        Route::get('/distance/overall', [DistanceController::class, 'overall']);

        // Device registration (FCM alias)
        Route::post('/devices/register', [DeviceController::class, 'register']);

        // Customer feedback
        Route::post('/feedback/customer-satisfaction', [FeedbackController::class, 'customerSatisfaction']);

        // Wallets
        Route::get('/wallets/{user_id}', [WalletController::class, 'showByUser']);
        Route::get('/wallets/{user_id}/transactions', [WalletController::class, 'transactionsByUser']);
        Route::get('/wallets/my/wallet', [WalletController::class, 'myWallet']);
        Route::get('/wallets/my/transactions', [WalletController::class, 'myTransactions']);
        Route::get('/wallets/my/validation', [WalletController::class, 'myWalletValidation']);
        Route::get('/wallets/my/linked-accounts', [WalletController::class, 'linkedAccounts']);
        Route::post('/wallets/my/linked-accounts', [WalletController::class, 'linkAccount']);
        Route::delete('/wallets/my/linked-accounts/{provider}', [WalletController::class, 'unlinkAccount']);
        Route::post('/wallets/my/linked-accounts/{provider}/verify', [WalletController::class, 'verifyLinkedAccount']);
        Route::post('/wallets/my/topup', [WalletController::class, 'topUp']);
        Route::post('/wallets/my/withdraw', [WalletController::class, 'withdraw']);
        Route::get('/wallets/threshold', [WalletController::class, 'getThreshold']);

        // Payment Gateway — mobile app (LesPay / Xendit)
        Route::prefix('payment-gateway')->group(function () {
            Route::post('/invoices', [PaymentGatewayInvoicesController::class, 'createInvoice']);
            Route::get('/invoices/{invoiceId}', [PaymentGatewayInvoicesController::class, 'getInvoice']);
            Route::get('/wallet/validate', [PaymentGatewayInvoicesController::class, 'validateWallet']);
            Route::get('/wallet/threshold', [PaymentGatewayInvoicesController::class, 'walletThreshold']);
            Route::post('/refunds', [PaymentGatewayInvoicesController::class, 'requestRefund']);
        });
        
        // Vouchers
        Route::get('/vouchers/available', [\App\Http\Controllers\Api\VoucherController::class, 'getAvailableVouchers']);
        Route::post('/vouchers/validate', [\App\Http\Controllers\Api\VoucherController::class, 'validateVoucher']);
        Route::post('/vouchers/claim', [\App\Http\Controllers\Api\VoucherController::class, 'claimVoucher']);

        // Payments
        Route::get('/payments', [PaymentController::class, 'index']);
        Route::post('/payments', [PaymentController::class, 'store']);
        Route::get('/payments/{payment}', [PaymentController::class, 'show']);

        // Notifications
        Route::get('/notifications', [NotificationController::class, 'index']);
        Route::get('/notifications/unread-count', [NotificationController::class, 'unreadCount']);
        Route::patch('/notifications/{id}/read', [NotificationController::class, 'markRead']);
        Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead']);

        // Payment Gateway (Xendit)
        Route::post('/gateway/invoice', [PaymentGatewayController::class, 'createInvoice']);
        Route::get('/gateway/invoice/{invoiceId}', [PaymentGatewayController::class, 'getInvoice']);
        Route::post('/gateway/invoice/{invoiceId}/expire', [PaymentGatewayController::class, 'expireInvoice']);
        Route::post('/gateway/refund', [PaymentGatewayController::class, 'refund']);

        // ── CUSTOMER EXPERIENCE FEATURES ──────────────────────────────────────

        // Rating & Review System
        Route::prefix('reviews')->group(function () {
            Route::get('/', [\App\Http\Controllers\Api\RatingReviewController::class, 'index']);
            Route::post('/', [\App\Http\Controllers\Api\RatingReviewController::class, 'store']);
            Route::get('/my-reviews', [\App\Http\Controllers\Api\RatingReviewController::class, 'myReviews']);
            Route::get('/statistics', [\App\Http\Controllers\Api\RatingReviewController::class, 'statistics']);
            Route::get('/{review}', [\App\Http\Controllers\Api\RatingReviewController::class, 'show']);
            Route::put('/{review}', [\App\Http\Controllers\Api\RatingReviewController::class, 'update']);
        });

        // Support Ticket System
        Route::prefix('support')->group(function () {
            Route::get('/tickets', [\App\Http\Controllers\Api\SupportTicketController::class, 'index']);
            Route::post('/tickets', [\App\Http\Controllers\Api\SupportTicketController::class, 'store']);
            Route::get('/tickets/statistics', [\App\Http\Controllers\Api\SupportTicketController::class, 'statistics']);
            Route::get('/tickets/{ticket}', [\App\Http\Controllers\Api\SupportTicketController::class, 'show']);
            Route::post('/tickets/{ticket}/messages', [\App\Http\Controllers\Api\SupportTicketController::class, 'addMessage']);
            Route::post('/tickets/{ticket}/close', [\App\Http\Controllers\Api\SupportTicketController::class, 'close']);
            Route::post('/tickets/{ticket}/satisfaction', [\App\Http\Controllers\Api\SupportTicketController::class, 'rateSatisfaction']);
        });

        // FAQ & Help Center
        Route::prefix('faq')->group(function () {
            Route::get('/categories', [\App\Http\Controllers\Api\FaqController::class, 'categories']);
            Route::get('/categories/{category}', [\App\Http\Controllers\Api\FaqController::class, 'categoryArticles']);
            Route::get('/articles', [\App\Http\Controllers\Api\FaqController::class, 'articles']);
            Route::get('/articles/{article}', [\App\Http\Controllers\Api\FaqController::class, 'article']);
            Route::get('/search', [\App\Http\Controllers\Api\FaqController::class, 'search']);
            Route::get('/featured', [\App\Http\Controllers\Api\FaqController::class, 'featured']);
            Route::get('/popular', [\App\Http\Controllers\Api\FaqController::class, 'popular']);
            Route::get('/statistics', [\App\Http\Controllers\Api\FaqController::class, 'statistics']);
            Route::post('/articles/{article}/helpful', [\App\Http\Controllers\Api\FaqController::class, 'markHelpful']);
            Route::post('/articles/{article}/not-helpful', [\App\Http\Controllers\Api\FaqController::class, 'markNotHelpful']);
        });

        // Live Order Tracking
        Route::prefix('tracking')->group(function () {
            Route::get('/orders/{order}', [\App\Http\Controllers\Api\OrderTrackingController::class, 'trackOrder']);
            Route::get('/orders/{order}/location', [\App\Http\Controllers\Api\OrderTrackingController::class, 'liveLocation']);
            Route::post('/orders/{order}/events', [\App\Http\Controllers\Api\OrderTrackingController::class, 'addEvent']);
            Route::post('/orders/multiple', [\App\Http\Controllers\Api\OrderTrackingController::class, 'trackMultiple']);
        });

        // ── DOCUMENT VERIFICATION SYSTEM ──────────────────────────────────────

        // Document Submission (for users)
        Route::prefix('documents')->group(function () {
            Route::post('/submit', [\App\Http\Controllers\Api\DocumentSubmissionController::class, 'submit']);
            Route::get('/my-documents', [\App\Http\Controllers\Api\DocumentSubmissionController::class, 'myDocuments']);
            Route::get('/types', [\App\Http\Controllers\Api\DocumentSubmissionController::class, 'documentTypes']);
            Route::get('/verification-status', [\App\Http\Controllers\Api\DocumentSubmissionController::class, 'verificationStatus']);
            Route::get('/{document}', [\App\Http\Controllers\Api\DocumentSubmissionController::class, 'show']);
            Route::post('/{document}/resubmit', [\App\Http\Controllers\Api\DocumentSubmissionController::class, 'resubmit']);
        });

        // Admin Document Verification (admin only)
        Route::prefix('admin/documents')->group(function () {
            Route::get('/', [\App\Http\Controllers\Api\Admin\DocumentVerificationController::class, 'index']);
            Route::get('/statistics', [\App\Http\Controllers\Api\Admin\DocumentVerificationController::class, 'statistics']);
            Route::get('/users-with-pending', [\App\Http\Controllers\Api\Admin\DocumentVerificationController::class, 'usersWithPendingDocuments']);
            Route::post('/bulk-approve', [\App\Http\Controllers\Api\Admin\DocumentVerificationController::class, 'bulkApprove']);
            Route::get('/user/{user}/history', [\App\Http\Controllers\Api\Admin\DocumentVerificationController::class, 'userHistory']);
            Route::get('/{document}', [\App\Http\Controllers\Api\Admin\DocumentVerificationController::class, 'show']);
            Route::post('/{document}/start-review', [\App\Http\Controllers\Api\Admin\DocumentVerificationController::class, 'startReview']);
            Route::post('/{document}/approve', [\App\Http\Controllers\Api\Admin\DocumentVerificationController::class, 'approve']);
            Route::post('/{document}/reject', [\App\Http\Controllers\Api\Admin\DocumentVerificationController::class, 'reject']);
        });

        // Admin Wallet Settings (admin only)
        Route::prefix('admin/wallet-settings')->group(function () {
            Route::get('/threshold', [\App\Http\Controllers\Api\Admin\WalletSettingsController::class, 'getThreshold']);
            Route::put('/threshold', [\App\Http\Controllers\Api\Admin\WalletSettingsController::class, 'updateThreshold']);
        });

        // Admin merchant mission reset (non-production only)
        Route::post('/admin/merchant-missions/reset', [\App\Http\Controllers\Api\Admin\MerchantMissionAdminController::class, 'reset'])
            ->middleware('role:admin');

        // ── SOCIAL MEDIA INTEGRATION ──────────────────────────────────────────

        // Social Media Sharing
        Route::prefix('social')->group(function () {
            Route::get('/platforms', [\App\Http\Controllers\Api\SocialMediaController::class, 'platforms']);
            Route::get('/platforms/{platform}/guidelines', [\App\Http\Controllers\Api\SocialMediaController::class, 'platformGuidelines']);
            Route::post('/orders/{order}/share', [\App\Http\Controllers\Api\SocialMediaController::class, 'generateOrderShare']);
            Route::post('/referral/share', [\App\Http\Controllers\Api\SocialMediaController::class, 'generateReferralShare']);
            Route::post('/follow/confirm', [\App\Http\Controllers\Api\SocialMediaController::class, 'confirmSocialFollow']);
            Route::post('/milestone/share', [\App\Http\Controllers\Api\SocialMediaController::class, 'generateMilestoneShare']);
            Route::get('/my-shares', [\App\Http\Controllers\Api\SocialMediaController::class, 'myShares']);
            Route::get('/analytics', [\App\Http\Controllers\Api\SocialMediaController::class, 'analytics']);
            Route::post('/shares/{share}/track', [\App\Http\Controllers\Api\SocialMediaController::class, 'trackEngagement']);
        });

        // ── GEOFENCING SYSTEM ──────────────────────────────────────────────────

        // Geofencing Management
        Route::prefix('geofences')->group(function () {
            Route::get('/', [\App\Http\Controllers\Api\GeofenceController::class, 'index']);
            Route::post('/', [\App\Http\Controllers\Api\GeofenceController::class, 'store']);
            Route::get('/types', [\App\Http\Controllers\Api\GeofenceController::class, 'types']);
            Route::get('/nearby', [\App\Http\Controllers\Api\GeofenceController::class, 'nearby']);
            Route::get('/statistics', [\App\Http\Controllers\Api\GeofenceController::class, 'statistics']);
            Route::get('/{geofence}', [\App\Http\Controllers\Api\GeofenceController::class, 'show']);
            Route::put('/{geofence}', [\App\Http\Controllers\Api\GeofenceController::class, 'update']);
            Route::delete('/{geofence}', [\App\Http\Controllers\Api\GeofenceController::class, 'destroy']);
            Route::post('/{geofence}/toggle', [\App\Http\Controllers\Api\GeofenceController::class, 'toggle']);
            Route::get('/{geofence}/events', [\App\Http\Controllers\Api\GeofenceController::class, 'events']);
            Route::post('/location/check', [\App\Http\Controllers\Api\GeofenceController::class, 'checkLocation']);
            Route::post('/check', [\App\Http\Controllers\Api\GeofenceController::class, 'checkLocation']);
            Route::post('/location/process', [\App\Http\Controllers\Api\GeofenceController::class, 'processLocation']);
        });

        // ── REAL-TIME FEATURES ─────────────────────────────────────────────────

        // WebSocket Connection Management
        Route::prefix('realtime')->group(function () {
            Route::post('/connect', [\App\Http\Controllers\Api\RealtimeController::class, 'connect']);
            Route::post('/disconnect', [\App\Http\Controllers\Api\RealtimeController::class, 'disconnect']);
            Route::post('/ping', [\App\Http\Controllers\Api\RealtimeController::class, 'ping']);
            Route::get('/connections', [\App\Http\Controllers\Api\RealtimeController::class, 'connections']);
            Route::get('/notifications', [\App\Http\Controllers\Api\RealtimeController::class, 'notifications']);
            Route::post('/notifications/{notification}/read', [\App\Http\Controllers\Api\RealtimeController::class, 'markNotificationRead']);
            Route::post('/notifications/read-all', [\App\Http\Controllers\Api\RealtimeController::class, 'markAllNotificationsRead']);
            Route::get('/stats', [\App\Http\Controllers\Api\RealtimeController::class, 'stats']);
            Route::post('/test-notification', [\App\Http\Controllers\Api\RealtimeController::class, 'testNotification']);
        });

        // Live Chat System
        Route::prefix('chat')->group(function () {
            Route::get('/conversations', [\App\Http\Controllers\Api\ChatController::class, 'conversations']);
            Route::get('/conversations/order/{order}', [\App\Http\Controllers\Api\ChatController::class, 'getOrCreateConversation']);
            Route::get('/conversations/{conversation}/messages', [\App\Http\Controllers\Api\ChatController::class, 'messages']);
            Route::post('/conversations/{conversation}/messages', [\App\Http\Controllers\Api\ChatController::class, 'sendMessage']);
            Route::post('/conversations/{conversation}/system-message', [\App\Http\Controllers\Api\ChatController::class, 'sendSystemMessage']);
            Route::post('/conversations/{conversation}/end', [\App\Http\Controllers\Api\ChatController::class, 'endConversation']);
            Route::post('/conversations/{conversation}/read', [\App\Http\Controllers\Api\ChatController::class, 'markMessagesAsRead']);
            Route::get('/unread-count', [\App\Http\Controllers\Api\ChatController::class, 'unreadCount']);
        });

        // Live Tracking System
        Route::prefix('tracking')->group(function () {
            Route::post('/driver/location', [\App\Http\Controllers\Api\LiveTrackingController::class, 'updateDriverLocation']);
            Route::get('/driver/{driver}/location', [\App\Http\Controllers\Api\LiveTrackingController::class, 'getDriverLocation']);
            Route::get('/driver/{driver}/history', [\App\Http\Controllers\Api\LiveTrackingController::class, 'getDriverLocationHistory']);
            Route::get('/order/{order}/live', [\App\Http\Controllers\Api\LiveTrackingController::class, 'getOrderTracking']);
            Route::get('/order/{order}/eta', [\App\Http\Controllers\Api\LiveTrackingController::class, 'getEta']);
            Route::get('/route', [\App\Http\Controllers\Api\LiveTrackingController::class, 'getRoute']);
            Route::get('/drivers/nearby', [\App\Http\Controllers\Api\LiveTrackingController::class, 'getNearbyDrivers']);
            Route::get('/stats', [\App\Http\Controllers\Api\LiveTrackingController::class, 'getTrackingStats']);
        });

        // ── DRIVER MISSIONS & REWARDS ─────────────────────────────────────────

        // Driver Missions
        Route::prefix('driver/missions')->group(function () {
            Route::get('/', [\App\Http\Controllers\Api\DriverMissionController::class, 'index']);
            Route::post('/{mission}/claim', [\App\Http\Controllers\Api\DriverMissionController::class, 'claim']);
        });

        // ── CUSTOMER MISSIONS & REWARDS ───────────────────────────────────────

        // Customer Missions
        Route::prefix('missions')->group(function () {
            Route::get('/', [\App\Http\Controllers\Api\CustomerMissionController::class, 'index']);
            Route::post('/{mission}/claim', [\App\Http\Controllers\Api\CustomerMissionController::class, 'claim']);
            Route::post('/{mission}/progress', [\App\Http\Controllers\Api\CustomerMissionController::class, 'recordProgress']);
        });

        // Loyalty rewards
        Route::prefix('loyalty')->group(function () {
            Route::get('/rewards', [\App\Http\Controllers\Api\LoyaltyController::class, 'rewards']);
            Route::post('/rewards/{rewardId}/redeem', [\App\Http\Controllers\Api\LoyaltyController::class, 'redeem']);
        });

        // Referral dashboard
        Route::get('/referrals/dashboard', [\App\Http\Controllers\Api\ReferralController::class, 'dashboard']);

        // ── MERCHANT MISSIONS & REWARDS ───────────────────────────────────────

        // Merchant Missions
        Route::prefix('merchant/missions')->group(function () {
            Route::get('/', [\App\Http\Controllers\Api\MerchantMissionController::class, 'index']);
            Route::post('/{mission}/claim', [\App\Http\Controllers\Api\MerchantMissionController::class, 'claim']);
        });

        // ── ADVANCED ANALYTICS & BUSINESS INTELLIGENCE ────────────────────────

        // Analytics Dashboard
        Route::prefix('analytics')->group(function () {
            Route::get('/dashboard', [\App\Http\Controllers\Api\AnalyticsController::class, 'dashboard']);
            Route::get('/revenue', [\App\Http\Controllers\Api\AnalyticsController::class, 'revenue']);
            Route::get('/drivers/performance', [\App\Http\Controllers\Api\AnalyticsController::class, 'driverPerformance']);
            Route::get('/customers/behavior', [\App\Http\Controllers\Api\AnalyticsController::class, 'customerBehavior']);
            Route::get('/services/demand', [\App\Http\Controllers\Api\AnalyticsController::class, 'serviceDemand']);
            Route::get('/geofences/effectiveness', [\App\Http\Controllers\Api\AnalyticsController::class, 'geofenceAnalytics']);
            Route::get('/predictions', [\App\Http\Controllers\Api\AnalyticsController::class, 'predictions']);
            Route::get('/events', [\App\Http\Controllers\Api\AnalyticsController::class, 'events']);
            Route::post('/events/track', [\App\Http\Controllers\Api\AnalyticsController::class, 'trackEvent']);
            Route::post('/export', [\App\Http\Controllers\Api\AnalyticsController::class, 'export']);
        });

        // ── ADVANCED SECURITY & COMPLIANCE ────────────────────────────────────

        // Security Dashboard & Management
        Route::prefix('security')->group(function () {
            Route::get('/dashboard', [\App\Http\Controllers\Api\SecurityController::class, 'dashboard']);
            
            // Two-Factor Authentication
            Route::prefix('2fa')->group(function () {
                Route::post('/setup', [\App\Http\Controllers\Api\SecurityController::class, 'setup2FA']);
                Route::post('/verify', [\App\Http\Controllers\Api\SecurityController::class, 'verify2FA']);
                Route::post('/disable', [\App\Http\Controllers\Api\SecurityController::class, 'disable2FA']);
                Route::post('/backup-codes/regenerate', [\App\Http\Controllers\Api\SecurityController::class, 'regenerateBackupCodes']);
            });

            // Biometric Authentication
            Route::prefix('biometric')->group(function () {
                Route::post('/enroll', [\App\Http\Controllers\Api\SecurityController::class, 'enrollBiometric']);
                Route::post('/verify', [\App\Http\Controllers\Api\SecurityController::class, 'verifyBiometric']);
                Route::get('/list', [\App\Http\Controllers\Api\SecurityController::class, 'getBiometrics']);
                Route::post('/deactivate', [\App\Http\Controllers\Api\SecurityController::class, 'deactivateBiometric']);
            });

            // Audit Logs & Security Events
            Route::prefix('audit')->group(function () {
                Route::get('/logs', [\App\Http\Controllers\Api\SecurityController::class, 'getAuditLogs']);
                Route::get('/events', [\App\Http\Controllers\Api\SecurityController::class, 'getSecurityEvents']);
                Route::post('/events/{event}/resolve', [\App\Http\Controllers\Api\SecurityController::class, 'resolveSecurityEvent']);
            });

            // Legacy mobile path alias
            Route::get('/audit-logs', [\App\Http\Controllers\Api\SecurityController::class, 'getAuditLogs']);

            // GDPR Compliance
            Route::prefix('gdpr')->group(function () {
                Route::post('/requests', [\App\Http\Controllers\Api\SecurityController::class, 'createGdprRequest']);
                Route::get('/requests', [\App\Http\Controllers\Api\SecurityController::class, 'getGdprRequests']);
            });

            // IP Management (Admin only)
            Route::prefix('ip')->middleware('role:admin')->group(function () {
                Route::get('/whitelist', [\App\Http\Controllers\Api\SecurityController::class, 'getIpWhitelist']);
                Route::post('/whitelist', [\App\Http\Controllers\Api\SecurityController::class, 'addToWhitelist']);
                Route::get('/blacklist', [\App\Http\Controllers\Api\SecurityController::class, 'getIpBlacklist']);
                Route::post('/blacklist', [\App\Http\Controllers\Api\SecurityController::class, 'addToBlacklist']);
            });
        });
    });
});
