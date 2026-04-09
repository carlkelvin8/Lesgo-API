<?php

// Complete OpenAPI 3.0 specification for LeSGo API
return [
    'openapi' => '3.0.0',
    'info' => [
        'title' => 'LeSGo API',
        'description' => 'Laravel 11 REST API for LeSGo logistics & multi-service platform with comprehensive endpoint documentation',
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
            ],
            'put' => [
                'tags' => ['Authentication'],
                'summary' => 'Update Profile',
                'description' => 'Update current user profile information',
                'security' => [['sanctum' => []]],
                'requestBody' => [
                    'required' => true,
                    'content' => [
                        'application/json' => [
                            'schema' => [
                                'type' => 'object',
                                'properties' => [
                                    'name' => ['type' => 'string', 'example' => 'John Doe'],
                                    'email' => ['type' => 'string', 'format' => 'email', 'example' => 'john@example.com']
                                ]
                            ]
                        ]
                    ]
                ],
                'responses' => [
                    '200' => [
                        'description' => 'Profile updated successfully',
                        'content' => [
                            'application/json' => [
                                'schema' => ['$ref' => '#/components/schemas/ApiResponse']
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
        '/api/v1/auth/logout-all' => [
            'post' => [
                'tags' => ['Authentication'],
                'summary' => 'Logout All Sessions',
                'description' => 'Logout user from all devices and revoke all tokens',
                'security' => [['sanctum' => []]],
                'responses' => [
                    '200' => [
                        'description' => 'Logged out from all sessions',
                        'content' => [
                            'application/json' => [
                                'schema' => ['$ref' => '#/components/schemas/ApiResponse']
                            ]
                        ]
                    ]
                ]
            ]
        ],
        '/api/v1/auth/fcm-token' => [
            'post' => [
                'tags' => ['Authentication'],
                'summary' => 'Register FCM Token',
                'description' => 'Register Firebase Cloud Messaging token for push notifications',
                'security' => [['sanctum' => []]],
                'requestBody' => [
                    'required' => true,
                    'content' => [
                        'application/json' => [
                            'schema' => [
                                'type' => 'object',
                                'required' => ['fcm_token'],
                                'properties' => [
                                    'fcm_token' => ['type' => 'string', 'example' => 'fcm_token_string']
                                ]
                            ]
                        ]
                    ]
                ],
                'responses' => [
                    '200' => [
                        'description' => 'FCM token registered successfully',
                        'content' => [
                            'application/json' => [
                                'schema' => ['$ref' => '#/components/schemas/ApiResponse']
                            ]
                        ]
                    ]
                ]
            ]
        ],

        // Services endpoints
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

        // Driver endpoints
        '/api/v1/drivers/register' => [
            'post' => [
                'tags' => ['Drivers'],
                'summary' => 'Register Driver',
                'description' => 'Register a new driver profile',
                'requestBody' => [
                    'required' => true,
                    'content' => [
                        'application/json' => [
                            'schema' => [
                                'type' => 'object',
                                'required' => ['name', 'email', 'password', 'phone', 'license_number'],
                                'properties' => [
                                    'name' => ['type' => 'string', 'example' => 'John Driver'],
                                    'email' => ['type' => 'string', 'format' => 'email', 'example' => 'driver@example.com'],
                                    'password' => ['type' => 'string', 'minLength' => 8, 'example' => 'password123'],
                                    'phone' => ['type' => 'string', 'example' => '+639123456789'],
                                    'license_number' => ['type' => 'string', 'example' => 'DL123456789']
                                ]
                            ]
                        ]
                    ]
                ],
                'responses' => [
                    '201' => [
                        'description' => 'Driver registered successfully',
                        'content' => [
                            'application/json' => [
                                'schema' => ['$ref' => '#/components/schemas/ApiResponse']
                            ]
                        ]
                    ]
                ]
            ]
        ],
        '/api/v1/drivers' => [
            'get' => [
                'tags' => ['Drivers'],
                'summary' => 'List Drivers',
                'description' => 'Returns a paginated list of drivers',
                'security' => [['sanctum' => []]],
                'responses' => [
                    '200' => [
                        'description' => 'List of drivers',
                        'content' => [
                            'application/json' => [
                                'schema' => ['$ref' => '#/components/schemas/PaginatedResponse']
                            ]
                        ]
                    ]
                ]
            ]
        ],
        '/api/v1/drivers/{driverProfile}' => [
            'get' => [
                'tags' => ['Drivers'],
                'summary' => 'Get Driver Details',
                'description' => 'Returns details of a specific driver',
                'security' => [['sanctum' => []]],
                'parameters' => [
                    [
                        'name' => 'driverProfile',
                        'in' => 'path',
                        'description' => 'Driver Profile ID',
                        'required' => true,
                        'schema' => ['type' => 'integer']
                    ]
                ],
                'responses' => [
                    '200' => [
                        'description' => 'Driver details',
                        'content' => [
                            'application/json' => [
                                'schema' => ['$ref' => '#/components/schemas/ApiResponse']
                            ]
                        ]
                    ]
                ]
            ]
        ],
        '/api/v1/drivers/{driverProfile}/status' => [
            'patch' => [
                'tags' => ['Drivers'],
                'summary' => 'Update Driver Status',
                'description' => 'Update driver availability status',
                'security' => [['sanctum' => []]],
                'parameters' => [
                    [
                        'name' => 'driverProfile',
                        'in' => 'path',
                        'description' => 'Driver Profile ID',
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
                                    'status' => ['type' => 'string', 'enum' => ['available', 'busy', 'offline'], 'example' => 'available']
                                ]
                            ]
                        ]
                    ]
                ],
                'responses' => [
                    '200' => [
                        'description' => 'Driver status updated successfully',
                        'content' => [
                            'application/json' => [
                                'schema' => ['$ref' => '#/components/schemas/ApiResponse']
                            ]
                        ]
                    ]
                ]
            ]
        ],
        '/api/v1/drivers/{driverProfile}/location' => [
            'patch' => [
                'tags' => ['Drivers'],
                'summary' => 'Update Driver Location',
                'description' => 'Update driver current location',
                'security' => [['sanctum' => []]],
                'parameters' => [
                    [
                        'name' => 'driverProfile',
                        'in' => 'path',
                        'description' => 'Driver Profile ID',
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
                                'required' => ['latitude', 'longitude'],
                                'properties' => [
                                    'latitude' => ['type' => 'number', 'format' => 'float', 'example' => 14.5995],
                                    'longitude' => ['type' => 'number', 'format' => 'float', 'example' => 120.9842]
                                ]
                            ]
                        ]
                    ]
                ],
                'responses' => [
                    '200' => [
                        'description' => 'Driver location updated successfully',
                        'content' => [
                            'application/json' => [
                                'schema' => ['$ref' => '#/components/schemas/ApiResponse']
                            ]
                        ]
                    ]
                ]
            ]
        ],

        // Users endpoints
        '/api/v1/users' => [
            'get' => [
                'tags' => ['Users'],
                'summary' => 'List Users',
                'description' => 'Returns a paginated list of users',
                'security' => [['sanctum' => []]],
                'responses' => [
                    '200' => [
                        'description' => 'List of users',
                        'content' => [
                            'application/json' => [
                                'schema' => ['$ref' => '#/components/schemas/PaginatedResponse']
                            ]
                        ]
                    ]
                ]
            ],
            'post' => [
                'tags' => ['Users'],
                'summary' => 'Create User',
                'description' => 'Create a new user',
                'security' => [['sanctum' => []]],
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
                        'description' => 'User created successfully',
                        'content' => [
                            'application/json' => [
                                'schema' => ['$ref' => '#/components/schemas/ApiResponse']
                            ]
                        ]
                    ]
                ]
            ]
        ],
        '/api/v1/users/{user}' => [
            'get' => [
                'tags' => ['Users'],
                'summary' => 'Get User Details',
                'description' => 'Returns details of a specific user',
                'security' => [['sanctum' => []]],
                'parameters' => [
                    [
                        'name' => 'user',
                        'in' => 'path',
                        'description' => 'User ID',
                        'required' => true,
                        'schema' => ['type' => 'integer']
                    ]
                ],
                'responses' => [
                    '200' => [
                        'description' => 'User details',
                        'content' => [
                            'application/json' => [
                                'schema' => ['$ref' => '#/components/schemas/ApiResponse']
                            ]
                        ]
                    ]
                ]
            ],
            'patch' => [
                'tags' => ['Users'],
                'summary' => 'Update User',
                'description' => 'Update user information',
                'security' => [['sanctum' => []]],
                'parameters' => [
                    [
                        'name' => 'user',
                        'in' => 'path',
                        'description' => 'User ID',
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
                                'properties' => [
                                    'name' => ['type' => 'string', 'example' => 'John Doe'],
                                    'email' => ['type' => 'string', 'format' => 'email', 'example' => 'john@example.com']
                                ]
                            ]
                        ]
                    ]
                ],
                'responses' => [
                    '200' => [
                        'description' => 'User updated successfully',
                        'content' => [
                            'application/json' => [
                                'schema' => ['$ref' => '#/components/schemas/ApiResponse']
                            ]
                        ]
                    ]
                ]
            ],
            'delete' => [
                'tags' => ['Users'],
                'summary' => 'Delete User',
                'description' => 'Delete a user',
                'security' => [['sanctum' => []]],
                'parameters' => [
                    [
                        'name' => 'user',
                        'in' => 'path',
                        'description' => 'User ID',
                        'required' => true,
                        'schema' => ['type' => 'integer']
                    ]
                ],
                'responses' => [
                    '200' => [
                        'description' => 'User deleted successfully',
                        'content' => [
                            'application/json' => [
                                'schema' => ['$ref' => '#/components/schemas/ApiResponse']
                            ]
                        ]
                    ]
                ]
            ]
        ]
    ]
];