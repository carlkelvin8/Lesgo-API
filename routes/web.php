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

// Official Swagger UI (Laravel Cloud CSP compatible)
Route::get('/swagger', function () {
    $apiUrl = config('app.url') . '/api-docs.json';
    
    return response(
        '<!DOCTYPE html>
        <html>
        <head>
            <title>LeSGo API Documentation</title>
            <link rel="stylesheet" type="text/css" href="https://cdn.jsdelivr.net/npm/swagger-ui-dist@4.15.5/swagger-ui.css" />
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
            <script src="https://cdn.jsdelivr.net/npm/swagger-ui-dist@4.15.5/swagger-ui-bundle.js"></script>
            <script>
                window.onload = function() {
                    const ui = SwaggerUIBundle({
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
                        layout: "BaseLayout",
                        validatorUrl: null,
                        tryItOutEnabled: true,
                        supportedSubmitMethods: ["get", "post", "put", "delete", "patch"],
                        docExpansion: "list",
                        filter: true,
                        showRequestHeaders: true,
                        requestInterceptor: function(request) {
                            // Add any custom headers here if needed
                            return request;
                        }
                    });
                };
            </script>
        </body>
        </html>'
    )->header('Content-Type', 'text/html');
});

// Complete API Documentation JSON with ALL endpoints
Route::get('/api-docs.json', function () {
    $docs = [
        'openapi' => '3.0.0',
        'info' => [
            'title' => 'LeSGo API',
            'description' => 'Laravel 11 REST API for LeSGo logistics & multi-service platform - Complete documentation with ALL endpoints',
            'version' => '1.0.0',
            'contact' => [
                'name' => 'LeSGo API Support',
                'url' => config('app.url')
            ]
        ],
        'servers' => [
            ['url' => config('app.url'), 'description' => 'Production server']
        ],
        'paths' => [
            // System endpoints
            '/' => [
                'get' => [
                    'tags' => ['System'],
                    'summary' => 'API Root',
                    'description' => 'Returns basic API information',
                    'responses' => [
                        '200' => [
                            'description' => 'API information',
                            'content' => [
                                'application/json' => [
                                    'schema' => [
                                        'type' => 'object',
                                        'properties' => [
                                            'message' => ['type' => 'string'],
                                            'timestamp' => ['type' => 'string'],
                                            'environment' => ['type' => 'string'],
                                            'version' => ['type' => 'string']
                                        ]
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]
            ],
            '/health' => [
                'get' => [
                    'tags' => ['System'],
                    'summary' => 'Health Check',
                    'description' => 'Returns system health status',
                    'responses' => [
                        '200' => [
                            'description' => 'System health information',
                            'content' => [
                                'application/json' => [
                                    'schema' => [
                                        'type' => 'object',
                                        'properties' => [
                                            'status' => ['type' => 'string'],
                                            'timestamp' => ['type' => 'string'],
                                            'php_version' => ['type' => 'string'],
                                            'laravel_version' => ['type' => 'string']
                                        ]
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]
            ],
            '/api/v1/ping' => [
                'get' => [
                    'tags' => ['System'],
                    'summary' => 'API Health Check',
                    'description' => 'Returns detailed API health status and system information',
                    'responses' => [
                        '200' => [
                            'description' => 'API health status',
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
                                            'server_port' => ['type' => 'string'],
                                            'request_uri' => ['type' => 'string'],
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

            // Authentication endpoints
            '/api/v1/auth/register' => [
                'post' => [
                    'tags' => ['Authentication'],
                    'summary' => 'Register User',
                    'description' => 'Register a new user (customer, driver, or partner_admin)',
                    'requestBody' => [
                        'required' => true,
                        'content' => [
                            'application/json' => [
                                'schema' => [
                                    'type' => 'object',
                                    'required' => ['name', 'email', 'password', 'role'],
                                    'properties' => [
                                        'name' => ['type' => 'string', 'example' => 'John Doe'],
                                        'email' => ['type' => 'string', 'format' => 'email', 'example' => 'john@example.com'],
                                        'password' => ['type' => 'string', 'minLength' => 8, 'example' => 'password123'],
                                        'role' => ['type' => 'string', 'enum' => ['customer', 'driver', 'partner_admin'], 'example' => 'customer']
                                    ]
                                ]
                            ]
                        ]
                    ],
                    'responses' => [
                        '201' => [
                            'description' => 'User registered successfully',
                            'content' => [
                                'application/json' => [
                                    'schema' => ['$ref' => '#/components/schemas/ApiResponse']
                                ]
                            ]
                        ],
                        '422' => [
                            'description' => 'Validation error',
                            'content' => [
                                'application/json' => [
                                    'schema' => ['$ref' => '#/components/schemas/ValidationErrorResponse']
                                ]
                            ]
                        ]
                    ]
                ]
            ],
            '/api/v1/auth/login' => [
                'post' => [
                    'tags' => ['Authentication'],
                    'summary' => 'Login User',
                    'description' => 'Authenticate user and return Sanctum token',
                    'requestBody' => [
                        'required' => true,
                        'content' => [
                            'application/json' => [
                                'schema' => [
                                    'type' => 'object',
                                    'required' => ['email', 'password'],
                                    'properties' => [
                                        'email' => ['type' => 'string', 'format' => 'email', 'example' => 'john@example.com'],
                                        'password' => ['type' => 'string', 'example' => 'password123']
                                    ]
                                ]
                            ]
                        ]
                    ],
                    'responses' => [
                        '200' => [
                            'description' => 'Login successful',
                            'content' => [
                                'application/json' => [
                                    'schema' => [
                                        'type' => 'object',
                                        'properties' => [
                                            'success' => ['type' => 'boolean'],
                                            'message' => ['type' => 'string'],
                                            'request_id' => ['type' => 'string'],
                                            'data' => [
                                                'type' => 'object',
                                                'properties' => [
                                                    'token' => ['type' => 'string'],
                                                    'user' => ['type' => 'object']
                                                ]
                                            ]
                                        ]
                                    ]
                                ]
                            ]
                        ],
                        '401' => [
                            'description' => 'Invalid credentials',
                            'content' => [
                                'application/json' => [
                                    'schema' => ['$ref' => '#/components/schemas/ErrorResponse']
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
            ],
            'schemas' => [
                'ApiResponse' => [
                    'type' => 'object',
                    'properties' => [
                        'success' => ['type' => 'boolean'],
                        'message' => ['type' => 'string'],
                        'request_id' => ['type' => 'string'],
                        'data' => ['type' => 'object']
                    ]
                ],
                'ErrorResponse' => [
                    'type' => 'object',
                    'properties' => [
                        'success' => ['type' => 'boolean', 'example' => false],
                        'message' => ['type' => 'string'],
                        'request_id' => ['type' => 'string']
                    ]
                ],
                'ValidationErrorResponse' => [
                    'type' => 'object',
                    'properties' => [
                        'success' => ['type' => 'boolean', 'example' => false],
                        'message' => ['type' => 'string'],
                        'request_id' => ['type' => 'string'],
                        'errors' => ['type' => 'object']
                    ]
                ],
                'PaginatedResponse' => [
                    'type' => 'object',
                    'properties' => [
                        'success' => ['type' => 'boolean'],
                        'message' => ['type' => 'string'],
                        'request_id' => ['type' => 'string'],
                        'data' => ['type' => 'array', 'items' => ['type' => 'object']],
                        'meta' => [
                            'type' => 'object',
                            'properties' => [
                                'total' => ['type' => 'integer'],
                                'per_page' => ['type' => 'integer'],
                                'current_page' => ['type' => 'integer'],
                                'last_page' => ['type' => 'integer'],
                                'from' => ['type' => 'integer'],
                                'to' => ['type' => 'integer'],
                                'has_more' => ['type' => 'boolean']
                            ]
                        ],
                        'links' => [
                            'type' => 'object',
                            'properties' => [
                                'first' => ['type' => 'string'],
                                'last' => ['type' => 'string'],
                                'prev' => ['type' => 'string', 'nullable' => true],
                                'next' => ['type' => 'string', 'nullable' => true]
                            ]
                        ]
                    ]
                ]
            ]
        ]
    ];
    
    // Merge additional endpoints from the complete specification
    if (file_exists(resource_path('openapi/complete-spec.php'))) {
        $additionalPaths = include resource_path('openapi/complete-spec.php');
        $docs['paths'] = array_merge($docs['paths'], $additionalPaths);
    }
    
    return response()->json($docs);
});

// Add L5-Swagger route for official package integration
Route::get('/api/documentation', function () {
    return redirect('/swagger');
});

// Alternative route for L5-Swagger compatibility
Route::get('/docs/api-docs.json', function () {
    return redirect('/api-docs.json');
});

// Self-hosted Swagger UI (No external dependencies - CSP safe)
Route::get('/swagger-local', function () {
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
                .method { display: inline-block; padding: 4px 12px; border-radius: 4px; font-weight: bold; font-size: 12px; text-transform: uppercase; margin-right: 10px; }
                .get { background: #61affe; color: white; }
                .post { background: #49cc90; color: white; }
                .put { background: #fca130; color: white; }
                .patch { background: #50e3c2; color: white; }
                .delete { background: #f93e3e; color: white; }
                .path { font-family: monospace; font-size: 16px; margin: 10px 0; font-weight: bold; }
                .description { color: #666; margin: 10px 0; }
                .auth-required { color: #e74c3c; font-size: 12px; font-weight: bold; }
                .json-link { display: inline-block; margin-top: 20px; padding: 10px 20px; background: #007bff; color: white; text-decoration: none; border-radius: 4px; }
                .json-link:hover { background: #0056b3; }
                .status { padding: 20px; background: #d4edda; border: 1px solid #c3e6cb; border-radius: 4px; margin: 20px 0; }
                .category { margin: 30px 0; }
                .category h2 { color: #2c3e50; border-bottom: 2px solid #3498db; padding-bottom: 10px; }
                .try-button { background: #28a745; color: white; border: none; padding: 8px 16px; border-radius: 4px; cursor: pointer; margin-top: 10px; }
                .try-button:hover { background: #218838; }
            </style>
        </head>
        <body>
            <div class="container">
                <h1>🚀 LeSGo API Documentation</h1>
                <p class="subtitle">Laravel 11 REST API for LeSGo logistics & multi-service platform</p>
                
                <div class="status">
                    <strong>✅ API Status:</strong> Live and operational<br>
                    <strong>🌐 Base URL:</strong> <code>' . config('app.url') . '</code><br>
                    <strong>📋 Version:</strong> 1.0.0<br>
                    <strong>🔐 Authentication:</strong> Bearer Token (Sanctum)
                </div>

                <div class="category">
                    <h2>🔧 System Endpoints</h2>
                    <div class="endpoint">
                        <span class="method get">GET</span>
                        <div class="path">/api/v1/ping</div>
                        <div class="description">Health check endpoint - Returns API status, database connectivity, and system information</div>
                        <button class="try-button" onclick="window.open(\'' . config('app.url') . '/api/v1/ping\', \'_blank\')">Try it out</button>
                    </div>
                </div>

                <div class="category">
                    <h2>🔐 Authentication</h2>
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
                    <div class="endpoint">
                        <span class="method get">GET</span>
                        <div class="path">/api/v1/auth/me</div>
                        <div class="description">Get current authenticated user information</div>
                        <div class="auth-required">🔒 Requires Authentication</div>
                    </div>
                </div>

                <div class="category">
                    <h2>🚛 Services</h2>
                    <div class="endpoint">
                        <span class="method get">GET</span>
                        <div class="path">/api/v1/services</div>
                        <div class="description">List all available services - Returns paginated list of logistics services</div>
                        <button class="try-button" onclick="window.open(\'' . config('app.url') . '/api/v1/services\', \'_blank\')">Try it out</button>
                    </div>
                </div>

                <div class="category">
                    <h2>📦 Orders</h2>
                    <div class="endpoint">
                        <span class="method get">GET</span>
                        <div class="path">/api/v1/orders</div>
                        <div class="description">List orders (scoped by user role)</div>
                        <div class="auth-required">🔒 Requires Authentication</div>
                    </div>
                    <div class="endpoint">
                        <span class="method post">POST</span>
                        <div class="path">/api/v1/orders</div>
                        <div class="description">Create a new order</div>
                        <div class="auth-required">🔒 Requires Authentication</div>
                    </div>
                </div>

                <div class="category">
                    <h2>💳 Payments</h2>
                    <div class="endpoint">
                        <span class="method get">GET</span>
                        <div class="path">/api/v1/payments</div>
                        <div class="description">List payments</div>
                        <div class="auth-required">🔒 Requires Authentication</div>
                    </div>
                    <div class="endpoint">
                        <span class="method post">POST</span>
                        <div class="path">/api/v1/payments</div>
                        <div class="description">Record a new payment</div>
                        <div class="auth-required">🔒 Requires Authentication</div>
                    </div>
                </div>

                <div class="category">
                    <h2>👥 Users & Drivers</h2>
                    <div class="endpoint">
                        <span class="method get">GET</span>
                        <div class="path">/api/v1/users</div>
                        <div class="description">List users</div>
                        <div class="auth-required">🔒 Requires Authentication</div>
                    </div>
                    <div class="endpoint">
                        <span class="method get">GET</span>
                        <div class="path">/api/v1/drivers</div>
                        <div class="description">List drivers</div>
                        <div class="auth-required">🔒 Requires Authentication</div>
                    </div>
                </div>

                <p><strong>📄 Complete API Specification:</strong></p>
                <a href="' . $apiUrl . '" class="json-link" target="_blank">📄 View Complete OpenAPI JSON (56+ endpoints)</a>
                
                <p style="margin-top: 30px; color: #666; font-size: 14px;">
                    <strong>Note:</strong> This is a simplified view. The complete OpenAPI specification includes all 56+ endpoints with detailed request/response schemas, authentication, and parameter documentation.
                </p>
            </div>
        </body>
        </html>'
    )->header('Content-Type', 'text/html');
});