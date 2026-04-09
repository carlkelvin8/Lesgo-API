<?php
// This file contains the complete OpenAPI specification for all LeSGo API endpoints
// Based on routes/api.php analysis

$completeApiSpec = [
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
        ]
    ]
];

return $completeApiSpec;