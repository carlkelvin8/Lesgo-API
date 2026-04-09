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

// Test endpoint to verify deployment
Route::get('/test', function () {
    return response()->json([
        'message' => 'Test endpoint working',
        'timestamp' => now()->toISOString(),
        'deployment_version' => '2026-04-09-v3'
    ]);
});

// API Documentation redirect
Route::get('/docs', function () {
    return redirect('/api/documentation');
});

// Simple Swagger UI page
Route::get('/api/documentation', function () {
    return view('swagger-ui');
});

// Alternative: Direct HTML Swagger UI
Route::get('/swagger', function () {
    $apiUrl = config('app.url') . '/api-docs.json';
    
    return response(
        '<!DOCTYPE html>
        <html>
        <head>
            <title>LeSGo API Documentation</title>
            <link rel="stylesheet" type="text/css" href="https://unpkg.com/swagger-ui-dist@3.52.5/swagger-ui.css" />
            <style>
                html { box-sizing: border-box; overflow: -moz-scrollbars-vertical; overflow-y: scroll; }
                *, *:before, *:after { box-sizing: inherit; }
                body { margin:0; background: #fafafa; }
            </style>
        </head>
        <body>
            <div id="swagger-ui"></div>
            <script src="https://unpkg.com/swagger-ui-dist@3.52.5/swagger-ui-bundle.js"></script>
            <script>
                SwaggerUIBundle({
                    url: "' . $apiUrl . '",
                    dom_id: "#swagger-ui",
                    deepLinking: true,
                    presets: [
                        SwaggerUIBundle.presets.apis,
                        SwaggerUIBundle.presets.standalone
                    ],
                    plugins: [
                        SwaggerUIBundle.plugins.DownloadUrl
                    ],
                    layout: "StandaloneLayout"
                });
            </script>
        </body>
        </html>'
    )->header('Content-Type', 'text/html');
});

// Simple API Documentation JSON
Route::get('/api-docs.json', function () {
    $docs = [
        'openapi' => '3.0.0',
        'info' => [
            'title' => 'LeSGo API',
            'description' => 'Laravel 11 REST API for LeSGo logistics platform',
            'version' => '1.0.0'
        ],
        'servers' => [
            ['url' => config('app.url')]
        ],
        'paths' => [
            '/api/v1/ping' => [
                'get' => [
                    'summary' => 'Health check',
                    'responses' => [
                        '200' => ['description' => 'API is healthy']
                    ]
                ]
            ],
            '/api/v1/services' => [
                'get' => [
                    'summary' => 'List services',
                    'responses' => [
                        '200' => ['description' => 'List of services']
                    ]
                ]
            ]
        ]
    ];
    
    return response()->json($docs);
});
