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

// API Documentation JSON - Simple and reliable
Route::get('/api-docs.json', function () {
    return response()->json([
        'openapi' => '3.0.0',
        'info' => [
            'title' => 'LeSGo API',
            'description' => 'Laravel 11 REST API for LeSGo logistics & multi-service platform',
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
            '/' => [
                'get' => [
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
                    'summary' => 'List Services',
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
                                            'request_id' => ['type' => 'string'],
                                            'data' => [
                                                'type' => 'array',
                                                'items' => ['type' => 'object']
                                            ],
                                            'meta' => [
                                                'type' => 'object',
                                                'properties' => [
                                                    'total' => ['type' => 'integer'],
                                                    'per_page' => ['type' => 'integer'],
                                                    'current_page' => ['type' => 'integer'],
                                                    'last_page' => ['type' => 'integer']
                                                ]
                                            ]
                                        ]
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]
            ],
            '/api/v1/auth/register' => [
                'post' => [
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
                                        'name' => ['type' => 'string'],
                                        'email' => ['type' => 'string', 'format' => 'email'],
                                        'password' => ['type' => 'string', 'minLength' => 8],
                                        'role' => ['type' => 'string', 'enum' => ['customer', 'driver', 'partner_admin']]
                                    ]
                                ]
                            ]
                        ]
                    ],
                    'responses' => [
                        '201' => [
                            'description' => 'User registered successfully'
                        ],
                        '422' => [
                            'description' => 'Validation error'
                        ]
                    ]
                ]
            ],
            '/api/v1/auth/login' => [
                'post' => [
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
                                        'email' => ['type' => 'string', 'format' => 'email'],
                                        'password' => ['type' => 'string']
                                    ]
                                ]
                            ]
                        ]
                    ],
                    'responses' => [
                        '200' => [
                            'description' => 'Login successful'
                        ],
                        '401' => [
                            'description' => 'Invalid credentials'
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
                ]
            ]
        ]
    ], 200, [
        'Content-Type' => 'application/json',
        'Access-Control-Allow-Origin' => '*'
    ]);
});
