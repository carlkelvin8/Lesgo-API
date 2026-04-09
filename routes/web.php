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
    try {
        $path = storage_path('api-docs/api-docs.json');

        if (file_exists($path)) {
            return response()->file($path, [
                'Content-Type' => 'application/json',
            ]);
        }

        // Try to generate docs if they don't exist
        \Artisan::call('l5-swagger:generate');
        
        if (file_exists($path)) {
            return response()->file($path, [
                'Content-Type' => 'application/json',
            ]);
        }
    } catch (\Exception $e) {
        // Log the error but don't expose it
        \Log::error('Swagger generation failed: ' . $e->getMessage());
    }
    
    // Return basic API documentation structure as fallback
    return response()->json([
        'openapi' => '3.0.0',
        'info' => [
            'title' => 'LeSGo API',
            'description' => 'Laravel 11 REST API for LeSGo logistics platform',
            'version' => '1.0.0',
            'contact' => [
                'name' => 'LeSGo API Support',
                'url' => config('app.url')
            ]
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
                    'description' => 'Returns API health status and system information',
                    'responses' => [
                        '200' => [
                            'description' => 'API is healthy',
                            'content' => [
                                'application/json' => [
                                    'schema' => [
                                        'type' => 'object',
                                        'properties' => [
                                            'message' => ['type' => 'string'],
                                            'timestamp' => ['type' => 'string'],
                                            'environment' => ['type' => 'string'],
                                            'php_version' => ['type' => 'string'],
                                            'laravel_version' => ['type' => 'string'],
                                            'database' => ['type' => 'string'],
                                            'redis' => ['type' => 'string']
                                        ]
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]
            ],
            '/api/v1/services' => [
                'get' => [
                    'summary' => 'List all services',
                    'description' => 'Returns a paginated list of available services',
                    'responses' => [
                        '200' => [
                            'description' => 'List of services',
                            'content' => [
                                'application/json' => [
                                    'schema' => [
                                        'type' => 'object',
                                        'properties' => [
                                            'success' => ['type' => 'boolean'],
                                            'message' => ['type' => 'string'],
                                            'data' => ['type' => 'array'],
                                            'meta' => ['type' => 'object']
                                        ]
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]
            ]
        ],
        'components' => [
            'securitySchemes' => [
                'sanctum' => [
                    'type' => 'http',
                    'scheme' => 'bearer',
                    'bearerFormat' => 'JWT',
                    'description' => 'Enter your Sanctum token: Bearer {token}'
                ]
            ]
        ]
    ]);
})->name('l5-swagger.default.docs');
