<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\PartnerController;
use App\Http\Controllers\Api\PartnerBranchController;
use App\Http\Controllers\Api\AddressController;
use App\Http\Controllers\Api\DriverProfileController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\ServiceController;
use App\Http\Controllers\Api\WalletController;
use App\Http\Controllers\Api\PaymentController;

Route::prefix('v1')->group(function () {

    // Health check
    Route::get('/ping', function () {
        return response()->json(['message' => 'LeSGo API v1 OK']);
    });

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

    // Drivers
    Route::get('/drivers', [DriverProfileController::class, 'index']);
       Route::post('/drivers/register', [DriverProfileController::class, 'register']);
    Route::get('/drivers/{driverProfile}', [DriverProfileController::class, 'show']);
    Route::patch('/drivers/{driverProfile}/status', [DriverProfileController::class, 'updateStatus']);
    Route::patch('/drivers/{driverProfile}/location', [DriverProfileController::class, 'updateLocation']);

    // Orders
    Route::get('/orders', [OrderController::class, 'index']);
    Route::post('/orders', [OrderController::class, 'store']);
    Route::get('/orders/{order}', [OrderController::class, 'show']);
    Route::patch('/orders/{order}/status', [OrderController::class, 'updateStatus']);

    // Services
    Route::get('/services', [ServiceController::class, 'index']);
    Route::get('/services/{service}', [ServiceController::class, 'show']);

    // Wallets
    Route::get('/wallets/{user_id}', [WalletController::class, 'showByUser']);
    Route::get('/wallets/{user_id}/transactions', [WalletController::class, 'transactionsByUser']);

    // Payments
    Route::get('/payments', [PaymentController::class, 'index']);
    Route::post('/payments', [PaymentController::class, 'store']);
    Route::get('/payments/{payment}', [PaymentController::class, 'show']);
});
