<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response()->json([
        'message' => 'LeSGo API is running',
        'timestamp' => now()->toISOString(),
        'environment' => app()->environment(),
        'version' => '1.0.0'
    ]);
});

// Health check route
Route::get('/health', function () {
    return response()->json([
        'status' => 'healthy',
        'timestamp' => now()->toISOString(),
        'php_version' => PHP_VERSION,
        'laravel_version' => app()->version()
    ]);
});

// API Documentation redirect
Route::get('/docs', function () {
    return redirect('/api/documentation');
});

/*
|--------------------------------------------------------------------------
| Swagger docs JSON route (for L5-Swagger)
|--------------------------------------------------------------------------
| This serves storage/api-docs/api-docs.json
| Route name MUST be: l5-swagger.default.docs
|--------------------------------------------------------------------------
*/
Route::get('/api-docs.json', function () {
    $path = storage_path('api-docs/api-docs.json');

    if (! file_exists($path)) {
        // Try to generate docs if they don't exist
        try {
            \Artisan::call('l5-swagger:generate');
            if (file_exists($path)) {
                return response()->file($path, [
                    'Content-Type' => 'application/json',
                ]);
            }
        } catch (\Exception $e) {
            // If generation fails, return a basic API structure
        }
        
        // Return basic API documentation structure
        return response()->json([
            'openapi' => '3.0.0',
            'info' => [
                'title' => 'LeSGo API',
                'description' => 'Laravel 11 REST API for LeSGo logistics platform',
                'version' => '1.0.0'
            ],
            'servers' => [
                [
                    'url' => config('app.url'),
                    'description' => 'Production server'
                ]
            ],
            'paths' => [
                '/api/v1/ping' => [
                    'get' => [
                        'summary' => 'Health check endpoint',
                        'responses' => [
                            '200' => [
                                'description' => 'API is healthy'
                            ]
                        ]
                    ]
                ]
            ]
        ]);
    }

    return response()->file($path, [
        'Content-Type' => 'application/json',
    ]);
})->name('l5-swagger.default.docs');
