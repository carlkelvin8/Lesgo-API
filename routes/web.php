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

// Simple API Documentation page (no external dependencies)
Route::get('/documentation', function () {
    $apiUrl = config('app.url') . '/api-docs.json';
    
    return response(
        '<!DOCTYPE html>
        <html>
        <head>
            <title>LeSGo API Documentation</title>
            <meta charset="utf-8">
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <style>
                body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; margin: 0; padding: 20px; background: #fafafa; }
                .container { max-width: 1200px; margin: 0 auto; background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
                h1 { color: #2c3e50; margin-bottom: 10px; }
                .subtitle { color: #7f8c8d; margin-bottom: 30px; }
                .endpoint { background: #f8f9fa; border: 1px solid #e9ecef; border-radius: 6px; margin: 20px 0; padding: 20px; }
                .method { display: inline-block; padding: 4px 12px; border-radius: 4px; font-weight: bold; font-size: 12px; text-transform: uppercase; }
                .get { background: #61affe; color: white; }
                .post { background: #49cc90; color: white; }
                .path { font-family: monospace; font-size: 16px; margin: 10px 0; }
                .description { color: #666; margin: 10px 0; }
                .json-link { display: inline-block; margin-top: 20px; padding: 10px 20px; background: #007bff; color: white; text-decoration: none; border-radius: 4px; }
                .json-link:hover { background: #0056b3; }
                .status { padding: 20px; background: #d4edda; border: 1px solid #c3e6cb; border-radius: 4px; margin: 20px 0; }
            </style>
        </head>
        <body>
            <div class="container">
                <h1>LeSGo API Documentation</h1>
                <p class="subtitle">Laravel 11 REST API for LeSGo logistics & multi-service platform</p>
                
                <div class="status">
                    <strong>✅ API Status:</strong> Live and operational<br>
                    <strong>🌐 Base URL:</strong> <code>' . config('app.url') . '</code><br>
                    <strong>📋 Version:</strong> 1.0.0
                </div>

                <div class="endpoint">
                    <span class="method get">GET</span>
                    <div class="path">/api/v1/ping</div>
                    <div class="description">Health check endpoint - Returns API status, database connectivity, and system information</div>
                </div>

                <div class="endpoint">
                    <span class="method get">GET</span>
                    <div class="path">/api/v1/services</div>
                    <div class="description">List all available services - Returns paginated list of logistics services</div>
                </div>

                <div class="endpoint">
                    <span class="method post">POST</span>
                    <div class="path">/api/v1/auth/register</div>
                    <div class="description">Register new user - Create customer, driver, or partner_admin account</div>
                </div>

                <div class="endpoint">
                    <span class="method post">POST</span>
                    <div class="path">/api/v1/auth/login</div>
                    <div class="description">User authentication - Login and receive Sanctum bearer token</div>
                </div>

                <a href="' . $apiUrl . '" class="json-link" target="_blank">📄 View OpenAPI JSON Specification</a>
            </div>
        </body>
        </html>'
    )->header('Content-Type', 'text/html');
});

// Alternative: Direct HTML Swagger UI
Route::get('/swagger', function () {
    $apiUrl = config('app.url') . '/api-docs.json';
    
    return response(
        '<!DOCTYPE html>
        <html>
        <head>
            <title>LeSGo API Documentation</title>
            <link rel="stylesheet" type="text/css" href="https://cdn.jsdelivr.net/npm/swagger-ui-dist@3.52.5/swagger-ui.css" />
            <style>
                html { box-sizing: border-box; overflow: -moz-scrollbars-vertical; overflow-y: scroll; }
                *, *:before, *:after { box-sizing: inherit; }
                body { margin:0; background: #fafafa; }
                .swagger-ui .topbar { display: none; }
                .swagger-ui .info { margin: 20px 0; }
                .swagger-ui .info .title { color: #3b4151; font-size: 36px; }
            </style>
        </head>
        <body>
            <div id="swagger-ui"></div>
            <script src="https://cdn.jsdelivr.net/npm/swagger-ui-dist@3.52.5/swagger-ui-bundle.js"></script>
            <script>
                window.onload = function() {
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
                        layout: "StandaloneLayout",
                        validatorUrl: null,
                        tryItOutEnabled: true
                    });
                };
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
