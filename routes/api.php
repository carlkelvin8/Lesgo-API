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
use App\Http\Controllers\Api\ReceiptController;
use App\Http\Controllers\Api\DistanceController;
use App\Http\Controllers\Api\ChecklistTemplateController;

Route::prefix('v1')->group(function () {

    /* =========================
       PUBLIC
    ========================= */

    Route::get('/ping', function () {
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

        Route::middleware('auth:sanctum')->group(function () {
            Route::get('/me', [AuthController::class, 'me']);
            Route::put('/me', [AuthController::class, 'updateProfile']);
            Route::post('/logout', [AuthController::class, 'logout']);
            Route::post('/logout-all', [AuthController::class, 'logoutAll']);
            Route::post('/fcm-token', [AuthController::class, 'registerFcmToken']);
        });
    });

    // Services (public) - General API rate limit
    Route::middleware('throttle:api')->group(function () {
        Route::get('/services', [ServiceController::class, 'index']);
        Route::get('/services/{service}', [ServiceController::class, 'show']);
    });

    // Driver registration (public) - Stricter rate limiting
    Route::post('/drivers/register', [DriverProfileController::class, 'register'])
        ->middleware('throttle:driver-registration');

    // Payment webhooks (public — verified by X-CALLBACK-TOKEN, no Bearer token)
    Route::post('/webhooks/payments/{provider}', [PaymentController::class, 'webhook'])
        ->middleware('throttle:60,1')
        ->whereIn('provider', ['xendit', 'gcash', 'maya']);

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

        // Partners
        Route::get('/partners', [PartnerController::class, 'index']);
        Route::post('/partners', [PartnerController::class, 'store']);
        Route::get('/partners/{partner}', [PartnerController::class, 'show']);
        Route::patch('/partners/{partner}', [PartnerController::class, 'update']);

        // Partner branches
        Route::get('/partners/{partner_id}/branches', [PartnerBranchController::class, 'index']);
        Route::post('/partners/{partner_id}/branches', [PartnerBranchController::class, 'store']);
        Route::patch('/branches/{branch}', [PartnerBranchController::class, 'update']);
        Route::delete('/branches/{branch}', [PartnerBranchController::class, 'destroy']);

        // Addresses
        Route::get('/users/{user_id}/addresses', [AddressController::class, 'index']);
        Route::post('/users/{user_id}/addresses', [AddressController::class, 'store']);
        Route::patch('/addresses/{address}', [AddressController::class, 'update']);
        Route::delete('/addresses/{address}', [AddressController::class, 'destroy']);

        // Drivers (protected reads/updates)
        Route::get('/drivers', [DriverProfileController::class, 'index']);
        Route::get('/drivers/{driverProfile}', [DriverProfileController::class, 'show']);
        Route::patch('/drivers/{driverProfile}/status', [DriverProfileController::class, 'updateStatus']);
        Route::patch('/drivers/{driverProfile}/location', [DriverProfileController::class, 'updateLocation']);

        // Orders
        Route::get('/orders', [OrderController::class, 'index']);
        Route::post('/orders', [OrderController::class, 'store']);
        Route::get('/orders/{order}', [OrderController::class, 'show']);
        Route::patch('/orders/{order}/status', [OrderController::class, 'updateStatus']);
        Route::get('/orders/{order}/receipt', [ReceiptController::class, 'show']);

        // Lesbuy Checklist
        Route::get('/checklist-templates', [ChecklistTemplateController::class, 'index']);
        Route::post('/checklist-templates', [ChecklistTemplateController::class, 'store']);

        // Distance
        Route::get('/distance/calculate', [DistanceController::class, 'calculate']);
        Route::get('/distance/overall', [DistanceController::class, 'overall']);

        // Wallets
        Route::get('/wallets/{user_id}', [WalletController::class, 'showByUser']);
        Route::get('/wallets/{user_id}/transactions', [WalletController::class, 'transactionsByUser']);

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
    });
});
