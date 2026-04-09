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
            <link rel="stylesheet" type="text/css" href="https://cdn.jsdelivr.net/npm/swagger-ui-dist@5.10.3/swagger-ui.css" />
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
            <script src="https://cdn.jsdelivr.net/npm/swagger-ui-dist@5.10.3/swagger-ui-bundle.js"></script>
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
                        tryItOutEnabled: true,
                        supportedSubmitMethods: ["get", "post", "put", "delete", "patch"],
                        docExpansion: "list",
                        filter: true,
                        showRequestHeaders: true
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
            'description' => 'Laravel 11 REST API for LeSGo logistics & multi-service platform - Complete documentation with all endpoints',
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
            '/api/v1/services' => [
                'get' => [
                    'tags' => ['Services'],
                    'summary' => 'List Services',
                    'description' => 'Returns a paginated list of available services',
                    'parameters' => [
                        [
                            'name' => 'page',
                            'in' => 'query',
                            'description' => 'Page number',
                            'required' => false,
                            'schema' => ['type' => 'integer', 'default' => 1]
                        ],
                        [
                            'name' => 'per_page',
                            'in' => 'query',
                            'description' => 'Items per page',
                            'required' => false,
                            'schema' => ['type' => 'integer', 'default' => 20]
                        ]
                    ],
                    'responses' => [
                        '200' => [
                            'description' => 'List of services',
                            'content' => [
                                'application/json' => [
                                    'schema' => ['$ref' => '#/components/schemas/PaginatedResponse']
                                ]
                            ]
                        ]
                    ]
                ]
            ],
            '/api/v1/services/{service}' => [
                'get' => [
                    'tags' => ['Services'],
                    'summary' => 'Get Service Details',
                    'description' => 'Returns details of a specific service',
                    'parameters' => [
                        [
                            'name' => 'service',
                            'in' => 'path',
                            'description' => 'Service ID',
                            'required' => true,
                            'schema' => ['type' => 'integer']
                        ]
                    ],
                    'responses' => [
                        '200' => [
                            'description' => 'Service details',
                            'content' => [
                                'application/json' => [
                                    'schema' => ['$ref' => '#/components/schemas/ApiResponse']
                                ]
                            ]
                        ],
                        '404' => [
                            'description' => 'Service not found',
                            'content' => [
                                'application/json' => [
                                    'schema' => ['$ref' => '#/components/schemas/ErrorResponse']
                                ]
                            ]
                        ]
                    ]
                ]
            ],
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
            ],
            '/api/v1/auth/me' => [
                'get' => [
                    'tags' => ['Authentication'],
                    'summary' => 'Get Current User',
                    'description' => 'Returns current authenticated user information',
                    'security' => [['sanctum' => []]],
                    'responses' => [
                        '200' => [
                            'description' => 'Current user information',
                            'content' => [
                                'application/json' => [
                                    'schema' => ['$ref' => '#/components/schemas/ApiResponse']
                                ]
                            ]
                        ],
                        '401' => [
                            'description' => 'Unauthenticated',
                            'content' => [
                                'application/json' => [
                                    'schema' => ['$ref' => '#/components/schemas/ErrorResponse']
                                ]
                            ]
                        ]
                    ]
                ]
            ],
            '/api/v1/auth/logout' => [
                'post' => [
                    'tags' => ['Authentication'],
                    'summary' => 'Logout User',
                    'description' => 'Logout current user and revoke token',
                    'security' => [['sanctum' => []]],
                    'responses' => [
                        '200' => [
                            'description' => 'Logout successful',
                            'content' => [
                                'application/json' => [
                                    'schema' => ['$ref' => '#/components/schemas/ApiResponse']
                                ]
                            ]
                        ]
                    ]
                ]
            ],
            '/api/v1/orders' => [
                'get' => [
                    'tags' => ['Orders'],
                    'summary' => 'List Orders',
                    'description' => 'Returns a paginated list of orders (scoped by user role)',
                    'security' => [['sanctum' => []]],
                    'parameters' => [
                        [
                            'name' => 'page',
                            'in' => 'query',
                            'description' => 'Page number',
                            'required' => false,
                            'schema' => ['type' => 'integer', 'default' => 1]
                        ],
                        [
                            'name' => 'status',
                            'in' => 'query',
                            'description' => 'Filter by order status',
                            'required' => false,
                            'schema' => ['type' => 'string', 'enum' => ['pending', 'confirmed', 'in_progress', 'completed', 'cancelled']]
                        ]
                    ],
                    'responses' => [
                        '200' => [
                            'description' => 'List of orders',
                            'content' => [
                                'application/json' => [
                                    'schema' => ['$ref' => '#/components/schemas/PaginatedResponse']
                                ]
                            ]
                        ]
                    ]
                ],
                'post' => [
                    'tags' => ['Orders'],
                    'summary' => 'Create Order',
                    'description' => 'Create a new order',
                    'security' => [['sanctum' => []]],
                    'requestBody' => [
                        'required' => true,
                        'content' => [
                            'application/json' => [
                                'schema' => [
                                    'type' => 'object',
                                    'required' => ['service_id', 'pickup_address', 'delivery_address'],
                                    'properties' => [
                                        'service_id' => ['type' => 'integer', 'example' => 1],
                                        'pickup_address' => ['type' => 'string', 'example' => '123 Main St, City'],
                                        'delivery_address' => ['type' => 'string', 'example' => '456 Oak Ave, City'],
                                        'notes' => ['type' => 'string', 'example' => 'Handle with care']
                                    ]
                                ]
                            ]
                        ]
                    ],
                    'responses' => [
                        '201' => [
                            'description' => 'Order created successfully',
                            'content' => [
                                'application/json' => [
                                    'schema' => ['$ref' => '#/components/schemas/ApiResponse']
                                ]
                            ]
                        ]
                    ]
                ]
            ],
            '/api/v1/orders/{order}' => [
                'get' => [
                    'tags' => ['Orders'],
                    'summary' => 'Get Order Details',
                    'description' => 'Returns details of a specific order',
                    'security' => [['sanctum' => []]],
                    'parameters' => [
                        [
                            'name' => 'order',
                            'in' => 'path',
                            'description' => 'Order ID',
                            'required' => true,
                            'schema' => ['type' => 'integer']
                        ]
                    ],
                    'responses' => [
                        '200' => [
                            'description' => 'Order details',
                            'content' => [
                                'application/json' => [
                                    'schema' => ['$ref' => '#/components/schemas/ApiResponse']
                                ]
                            ]
                        ],
                        '404' => [
                            'description' => 'Order not found',
                            'content' => [
                                'application/json' => [
                                    'schema' => ['$ref' => '#/components/schemas/ErrorResponse']
                                ]
                            ]
                        ]
                    ]
                ]
            ],
            '/api/v1/orders/{order}/status' => [
                'patch' => [
                    'tags' => ['Orders'],
                    'summary' => 'Update Order Status',
                    'description' => 'Update the status of an order',
                    'security' => [['sanctum' => []]],
                    'parameters' => [
                        [
                            'name' => 'order',
                            'in' => 'path',
                            'description' => 'Order ID',
                            'required' => true,
                            'schema' => ['type' => 'integer']
                        ]
                    ],
                    'requestBody' => [
                        'required' => true,
                        'content' => [
                            'application/json' => [
                                'schema' => [
                                    'type' => 'object',
                                    'required' => ['status'],
                                    'properties' => [
                                        'status' => ['type' => 'string', 'enum' => ['pending', 'confirmed', 'in_progress', 'completed', 'cancelled'], 'example' => 'confirmed']
                                    ]
                                ]
                            ]
                        ]
                    ],
                    'responses' => [
                        '200' => [
                            'description' => 'Order status updated successfully',
                            'content' => [
                                'application/json' => [
                                    'schema' => ['$ref' => '#/components/schemas/ApiResponse']
                                ]
                            ]
                        ]
                    ]
                ]
            ],
            '/api/v1/payments' => [
                'get' => [
                    'tags' => ['Payments'],
                    'summary' => 'List Payments',
                    'description' => 'Returns a paginated list of payments',
                    'security' => [['sanctum' => []]],
                    'responses' => [
                        '200' => [
                            'description' => 'List of payments',
                            'content' => [
                                'application/json' => [
                                    'schema' => ['$ref' => '#/components/schemas/PaginatedResponse']
                                ]
                            ]
                        ]
                    ]
                ],
                'post' => [
                    'tags' => ['Payments'],
                    'summary' => 'Record Payment',
                    'description' => 'Record a new payment',
                    'security' => [['sanctum' => []]],
                    'requestBody' => [
                        'required' => true,
                        'content' => [
                            'application/json' => [
                                'schema' => [
                                    'type' => 'object',
                                    'required' => ['order_id', 'amount', 'payment_method'],
                                    'properties' => [
                                        'order_id' => ['type' => 'integer', 'example' => 1],
                                        'amount' => ['type' => 'number', 'format' => 'float', 'example' => 100.50],
                                        'payment_method' => ['type' => 'string', 'enum' => ['cash', 'card', 'gcash', 'maya'], 'example' => 'gcash']
                                    ]
                                ]
                            ]
                        ]
                    ],
                    'responses' => [
                        '201' => [
                            'description' => 'Payment recorded successfully',
                            'content' => [
                                'application/json' => [
                                    'schema' => ['$ref' => '#/components/schemas/ApiResponse']
                                ]
                            ]
                        ]
                    ]
                ]
            ],
            '/api/v1/wallets/{userId}' => [
                'get' => [
                    'tags' => ['Wallets'],
                    'summary' => 'Get Wallet Balance',
                    'description' => 'Returns wallet balance for a user',
                    'security' => [['sanctum' => []]],
                    'parameters' => [
                        [
                            'name' => 'userId',
                            'in' => 'path',
                            'description' => 'User ID',
                            'required' => true,
                            'schema' => ['type' => 'integer']
                        ]
                    ],
                    'responses' => [
                        '200' => [
                            'description' => 'Wallet balance',
                            'content' => [
                                'application/json' => [
                                    'schema' => ['$ref' => '#/components/schemas/ApiResponse']
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
    
    return response()->json($docs);
});
