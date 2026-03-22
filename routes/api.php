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
use App\Http\Controllers\Api\ReceiptController;
use App\Http\Controllers\Api\DistanceController;
use App\Http\Controllers\Api\ChecklistTemplateController;

Route::prefix('v1')->group(function () {

    /* =========================
       PUBLIC
    ========================= */

    Route::get('/ping', fn () => response()->json(['message' => 'LeSGo API v1 OK']));

    // AUTH (public + protected) - Stricter rate limiting
    Route::prefix('auth')->middleware('throttle:auth')->group(function () {
        Route::post('/register', [AuthController::class, 'register']);
        Route::post('/login', [AuthController::class, 'login']);

        Route::middleware('auth:sanctum')->group(function () {
            Route::get('/me', [AuthController::class, 'me']);
            Route::put('/me', [AuthController::class, 'updateProfile']);
            Route::post('/logout', [AuthController::class, 'logout']);
            Route::post('/logout-all', [AuthController::class, 'logoutAll']);
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

    // Payment webhooks (public — signed by provider, no Bearer token)
    Route::post('/webhooks/payments/{provider}', [PaymentController::class, 'webhook'])
        ->middleware('throttle:60,1')
        ->whereIn('provider', ['gcash', 'maya', 'paymongo']);

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
    });
});
