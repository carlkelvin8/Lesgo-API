<?php

// Complete OpenAPI specification with ALL endpoints from routes/api.php
return [
    // Authentication endpoints (continued)
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
    ],

    // Orders endpoints
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
    '/api/v1/orders/{order}/receipt' => [
        'get' => [
            'tags' => ['Orders'],
            'summary' => 'Get Order Receipt',
            'description' => 'Returns receipt for a specific order',
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
                    'description' => 'Order receipt',
                    'content' => [
                        'application/json' => [
                            'schema' => ['$ref' => '#/components/schemas/ApiResponse']
                        ]
                    ]
                ]
            ]
        ]
    ],

    // Payments endpoints
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
    '/api/v1/payments/{payment}' => [
        'get' => [
            'tags' => ['Payments'],
            'summary' => 'Get Payment Details',
            'description' => 'Returns details of a specific payment',
            'security' => [['sanctum' => []]],
            'parameters' => [
                [
                    'name' => 'payment',
                    'in' => 'path',
                    'description' => 'Payment ID',
                    'required' => true,
                    'schema' => ['type' => 'integer']
                ]
            ],
            'responses' => [
                '200' => [
                    'description' => 'Payment details',
                    'content' => [
                        'application/json' => [
                            'schema' => ['$ref' => '#/components/schemas/ApiResponse']
                        ]
                    ]
                ]
            ]
        ]
    ],

    // Wallets endpoints
    '/api/v1/wallets/{user_id}' => [
        'get' => [
            'tags' => ['Wallets'],
            'summary' => 'Get Wallet Balance',
            'description' => 'Returns wallet balance for a user',
            'security' => [['sanctum' => []]],
            'parameters' => [
                [
                    'name' => 'user_id',
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
    ],
    '/api/v1/wallets/{user_id}/transactions' => [
        'get' => [
            'tags' => ['Wallets'],
            'summary' => 'Get Wallet Transactions',
            'description' => 'Returns transaction history for a user wallet',
            'security' => [['sanctum' => []]],
            'parameters' => [
                [
                    'name' => 'user_id',
                    'in' => 'path',
                    'description' => 'User ID',
                    'required' => true,
                    'schema' => ['type' => 'integer']
                ]
            ],
            'responses' => [
                '200' => [
                    'description' => 'Wallet transactions',
                    'content' => [
                        'application/json' => [
                            'schema' => ['$ref' => '#/components/schemas/PaginatedResponse']
                        ]
                    ]
                ]
            ]
        ]
    ],

    // Notifications endpoints
    '/api/v1/notifications' => [
        'get' => [
            'tags' => ['Notifications'],
            'summary' => 'List Notifications',
            'description' => 'Returns a paginated list of notifications for the authenticated user',
            'security' => [['sanctum' => []]],
            'responses' => [
                '200' => [
                    'description' => 'List of notifications',
                    'content' => [
                        'application/json' => [
                            'schema' => ['$ref' => '#/components/schemas/PaginatedResponse']
                        ]
                    ]
                ]
            ]
        ]
    ],
    '/api/v1/notifications/unread-count' => [
        'get' => [
            'tags' => ['Notifications'],
            'summary' => 'Get Unread Count',
            'description' => 'Returns count of unread notifications',
            'security' => [['sanctum' => []]],
            'responses' => [
                '200' => [
                    'description' => 'Unread notifications count',
                    'content' => [
                        'application/json' => [
                            'schema' => ['$ref' => '#/components/schemas/ApiResponse']
                        ]
                    ]
                ]
            ]
        ]
    ],
    '/api/v1/notifications/{id}/read' => [
        'patch' => [
            'tags' => ['Notifications'],
            'summary' => 'Mark Notification as Read',
            'description' => 'Mark a specific notification as read',
            'security' => [['sanctum' => []]],
            'parameters' => [
                [
                    'name' => 'id',
                    'in' => 'path',
                    'description' => 'Notification ID',
                    'required' => true,
                    'schema' => ['type' => 'integer']
                ]
            ],
            'responses' => [
                '200' => [
                    'description' => 'Notification marked as read',
                    'content' => [
                        'application/json' => [
                            'schema' => ['$ref' => '#/components/schemas/ApiResponse']
                        ]
                    ]
                ]
            ]
        ]
    ],
    '/api/v1/notifications/read-all' => [
        'post' => [
            'tags' => ['Notifications'],
            'summary' => 'Mark All Notifications as Read',
            'description' => 'Mark all notifications as read for the authenticated user',
            'security' => [['sanctum' => []]],
            'responses' => [
                '200' => [
                    'description' => 'All notifications marked as read',
                    'content' => [
                        'application/json' => [
                            'schema' => ['$ref' => '#/components/schemas/ApiResponse']
                        ]
                    ]
                ]
            ]
        ]
    ],

    // Partners endpoints
    '/api/v1/partners' => [
        'get' => [
            'tags' => ['Partners'],
            'summary' => 'List Partners',
            'description' => 'Returns a paginated list of partners',
            'security' => [['sanctum' => []]],
            'responses' => [
                '200' => [
                    'description' => 'List of partners',
                    'content' => [
                        'application/json' => [
                            'schema' => ['$ref' => '#/components/schemas/PaginatedResponse']
                        ]
                    ]
                ]
            ]
        ],
        'post' => [
            'tags' => ['Partners'],
            'summary' => 'Create Partner',
            'description' => 'Create a new partner',
            'security' => [['sanctum' => []]],
            'requestBody' => [
                'required' => true,
                'content' => [
                    'application/json' => [
                        'schema' => [
                            'type' => 'object',
                            'required' => ['name', 'email', 'phone'],
                            'properties' => [
                                'name' => ['type' => 'string', 'example' => 'Partner Company'],
                                'email' => ['type' => 'string', 'format' => 'email', 'example' => 'partner@example.com'],
                                'phone' => ['type' => 'string', 'example' => '+639123456789']
                            ]
                        ]
                    ]
                ]
            ],
            'responses' => [
                '201' => [
                    'description' => 'Partner created successfully',
                    'content' => [
                        'application/json' => [
                            'schema' => ['$ref' => '#/components/schemas/ApiResponse']
                        ]
                    ]
                ]
            ]
        ]
    ],
    '/api/v1/partners/{partner}' => [
        'get' => [
            'tags' => ['Partners'],
            'summary' => 'Get Partner Details',
            'description' => 'Returns details of a specific partner',
            'security' => [['sanctum' => []]],
            'parameters' => [
                [
                    'name' => 'partner',
                    'in' => 'path',
                    'description' => 'Partner ID',
                    'required' => true,
                    'schema' => ['type' => 'integer']
                ]
            ],
            'responses' => [
                '200' => [
                    'description' => 'Partner details',
                    'content' => [
                        'application/json' => [
                            'schema' => ['$ref' => '#/components/schemas/ApiResponse']
                        ]
                    ]
                ]
            ]
        ],
        'patch' => [
            'tags' => ['Partners'],
            'summary' => 'Update Partner',
            'description' => 'Update partner information',
            'security' => [['sanctum' => []]],
            'parameters' => [
                [
                    'name' => 'partner',
                    'in' => 'path',
                    'description' => 'Partner ID',
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
                                'name' => ['type' => 'string', 'example' => 'Partner Company'],
                                'email' => ['type' => 'string', 'format' => 'email', 'example' => 'partner@example.com'],
                                'phone' => ['type' => 'string', 'example' => '+639123456789']
                            ]
                        ]
                    ]
                ]
            ],
            'responses' => [
                '200' => [
                    'description' => 'Partner updated successfully',
                    'content' => [
                        'application/json' => [
                            'schema' => ['$ref' => '#/components/schemas/ApiResponse']
                        ]
                    ]
                ]
            ]
        ]
    ],

    // Partner Branches endpoints
    '/api/v1/partners/{partner_id}/branches' => [
        'get' => [
            'tags' => ['Partner Branches'],
            'summary' => 'List Partner Branches',
            'description' => 'Returns a list of branches for a specific partner',
            'security' => [['sanctum' => []]],
            'parameters' => [
                [
                    'name' => 'partner_id',
                    'in' => 'path',
                    'description' => 'Partner ID',
                    'required' => true,
                    'schema' => ['type' => 'integer']
                ]
            ],
            'responses' => [
                '200' => [
                    'description' => 'List of partner branches',
                    'content' => [
                        'application/json' => [
                            'schema' => ['$ref' => '#/components/schemas/PaginatedResponse']
                        ]
                    ]
                ]
            ]
        ],
        'post' => [
            'tags' => ['Partner Branches'],
            'summary' => 'Create Partner Branch',
            'description' => 'Create a new branch for a partner',
            'security' => [['sanctum' => []]],
            'parameters' => [
                [
                    'name' => 'partner_id',
                    'in' => 'path',
                    'description' => 'Partner ID',
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
                            'required' => ['name', 'address'],
                            'properties' => [
                                'name' => ['type' => 'string', 'example' => 'Main Branch'],
                                'address' => ['type' => 'string', 'example' => '123 Main St, City'],
                                'phone' => ['type' => 'string', 'example' => '+639123456789']
                            ]
                        ]
                    ]
                ]
            ],
            'responses' => [
                '201' => [
                    'description' => 'Partner branch created successfully',
                    'content' => [
                        'application/json' => [
                            'schema' => ['$ref' => '#/components/schemas/ApiResponse']
                        ]
                    ]
                ]
            ]
        ]
    ],
    '/api/v1/branches/{branch}' => [
        'patch' => [
            'tags' => ['Partner Branches'],
            'summary' => 'Update Partner Branch',
            'description' => 'Update partner branch information',
            'security' => [['sanctum' => []]],
            'parameters' => [
                [
                    'name' => 'branch',
                    'in' => 'path',
                    'description' => 'Branch ID',
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
                                'name' => ['type' => 'string', 'example' => 'Main Branch'],
                                'address' => ['type' => 'string', 'example' => '123 Main St, City'],
                                'phone' => ['type' => 'string', 'example' => '+639123456789']
                            ]
                        ]
                    ]
                ]
            ],
            'responses' => [
                '200' => [
                    'description' => 'Partner branch updated successfully',
                    'content' => [
                        'application/json' => [
                            'schema' => ['$ref' => '#/components/schemas/ApiResponse']
                        ]
                    ]
                ]
            ]
        ],
        'delete' => [
            'tags' => ['Partner Branches'],
            'summary' => 'Delete Partner Branch',
            'description' => 'Delete a partner branch',
            'security' => [['sanctum' => []]],
            'parameters' => [
                [
                    'name' => 'branch',
                    'in' => 'path',
                    'description' => 'Branch ID',
                    'required' => true,
                    'schema' => ['type' => 'integer']
                ]
            ],
            'responses' => [
                '200' => [
                    'description' => 'Partner branch deleted successfully',
                    'content' => [
                        'application/json' => [
                            'schema' => ['$ref' => '#/components/schemas/ApiResponse']
                        ]
                    ]
                ]
            ]
        ]
    ],

    // Addresses endpoints
    '/api/v1/users/{user_id}/addresses' => [
        'get' => [
            'tags' => ['Addresses'],
            'summary' => 'List User Addresses',
            'description' => 'Returns a list of addresses for a specific user',
            'security' => [['sanctum' => []]],
            'parameters' => [
                [
                    'name' => 'user_id',
                    'in' => 'path',
                    'description' => 'User ID',
                    'required' => true,
                    'schema' => ['type' => 'integer']
                ]
            ],
            'responses' => [
                '200' => [
                    'description' => 'List of user addresses',
                    'content' => [
                        'application/json' => [
                            'schema' => ['$ref' => '#/components/schemas/PaginatedResponse']
                        ]
                    ]
                ]
            ]
        ],
        'post' => [
            'tags' => ['Addresses'],
            'summary' => 'Create User Address',
            'description' => 'Create a new address for a user',
            'security' => [['sanctum' => []]],
            'parameters' => [
                [
                    'name' => 'user_id',
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
                            'required' => ['address', 'city'],
                            'properties' => [
                                'address' => ['type' => 'string', 'example' => '123 Main St'],
                                'city' => ['type' => 'string', 'example' => 'Manila'],
                                'province' => ['type' => 'string', 'example' => 'Metro Manila'],
                                'postal_code' => ['type' => 'string', 'example' => '1000']
                            ]
                        ]
                    ]
                ]
            ],
            'responses' => [
                '201' => [
                    'description' => 'Address created successfully',
                    'content' => [
                        'application/json' => [
                            'schema' => ['$ref' => '#/components/schemas/ApiResponse']
                        ]
                    ]
                ]
            ]
        ]
    ],
    '/api/v1/addresses/{address}' => [
        'patch' => [
            'tags' => ['Addresses'],
            'summary' => 'Update Address',
            'description' => 'Update address information',
            'security' => [['sanctum' => []]],
            'parameters' => [
                [
                    'name' => 'address',
                    'in' => 'path',
                    'description' => 'Address ID',
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
                                'address' => ['type' => 'string', 'example' => '123 Main St'],
                                'city' => ['type' => 'string', 'example' => 'Manila'],
                                'province' => ['type' => 'string', 'example' => 'Metro Manila'],
                                'postal_code' => ['type' => 'string', 'example' => '1000']
                            ]
                        ]
                    ]
                ]
            ],
            'responses' => [
                '200' => [
                    'description' => 'Address updated successfully',
                    'content' => [
                        'application/json' => [
                            'schema' => ['$ref' => '#/components/schemas/ApiResponse']
                        ]
                    ]
                ]
            ]
        ],
        'delete' => [
            'tags' => ['Addresses'],
            'summary' => 'Delete Address',
            'description' => 'Delete an address',
            'security' => [['sanctum' => []]],
            'parameters' => [
                [
                    'name' => 'address',
                    'in' => 'path',
                    'description' => 'Address ID',
                    'required' => true,
                    'schema' => ['type' => 'integer']
                ]
            ],
            'responses' => [
                '200' => [
                    'description' => 'Address deleted successfully',
                    'content' => [
                        'application/json' => [
                            'schema' => ['$ref' => '#/components/schemas/ApiResponse']
                        ]
                    ]
                ]
            ]
        ]
    ],

    // Checklist Templates endpoints
    '/api/v1/checklist-templates' => [
        'get' => [
            'tags' => ['Checklist Templates'],
            'summary' => 'List Checklist Templates',
            'description' => 'Returns a paginated list of checklist templates',
            'security' => [['sanctum' => []]],
            'responses' => [
                '200' => [
                    'description' => 'List of checklist templates',
                    'content' => [
                        'application/json' => [
                            'schema' => ['$ref' => '#/components/schemas/PaginatedResponse']
                        ]
                    ]
                ]
            ]
        ],
        'post' => [
            'tags' => ['Checklist Templates'],
            'summary' => 'Create Checklist Template',
            'description' => 'Create a new checklist template',
            'security' => [['sanctum' => []]],
            'requestBody' => [
                'required' => true,
                'content' => [
                    'application/json' => [
                        'schema' => [
                            'type' => 'object',
                            'required' => ['name', 'items'],
                            'properties' => [
                                'name' => ['type' => 'string', 'example' => 'Delivery Checklist'],
                                'items' => ['type' => 'array', 'items' => ['type' => 'string'], 'example' => ['Check package condition', 'Verify recipient identity']]
                            ]
                        ]
                    ]
                ]
            ],
            'responses' => [
                '201' => [
                    'description' => 'Checklist template created successfully',
                    'content' => [
                        'application/json' => [
                            'schema' => ['$ref' => '#/components/schemas/ApiResponse']
                        ]
                    ]
                ]
            ]
        ]
    ],

    // Distance endpoints
    '/api/v1/distance/calculate' => [
        'get' => [
            'tags' => ['Distance'],
            'summary' => 'Calculate Distance',
            'description' => 'Calculate distance between two points',
            'security' => [['sanctum' => []]],
            'parameters' => [
                [
                    'name' => 'from_lat',
                    'in' => 'query',
                    'description' => 'From latitude',
                    'required' => true,
                    'schema' => ['type' => 'number', 'format' => 'float']
                ],
                [
                    'name' => 'from_lng',
                    'in' => 'query',
                    'description' => 'From longitude',
                    'required' => true,
                    'schema' => ['type' => 'number', 'format' => 'float']
                ],
                [
                    'name' => 'to_lat',
                    'in' => 'query',
                    'description' => 'To latitude',
                    'required' => true,
                    'schema' => ['type' => 'number', 'format' => 'float']
                ],
                [
                    'name' => 'to_lng',
                    'in' => 'query',
                    'description' => 'To longitude',
                    'required' => true,
                    'schema' => ['type' => 'number', 'format' => 'float']
                ]
            ],
            'responses' => [
                '200' => [
                    'description' => 'Distance calculation result',
                    'content' => [
                        'application/json' => [
                            'schema' => ['$ref' => '#/components/schemas/ApiResponse']
                        ]
                    ]
                ]
            ]
        ]
    ],
    '/api/v1/distance/overall' => [
        'get' => [
            'tags' => ['Distance'],
            'summary' => 'Get Overall Distance',
            'description' => 'Get overall distance statistics',
            'security' => [['sanctum' => []]],
            'responses' => [
                '200' => [
                    'description' => 'Overall distance statistics',
                    'content' => [
                        'application/json' => [
                            'schema' => ['$ref' => '#/components/schemas/ApiResponse']
                        ]
                    ]
                ]
            ]
        ]
    ],

    // Payment Gateway endpoints
    '/api/v1/gateway/invoice' => [
        'post' => [
            'tags' => ['Payment Gateway'],
            'summary' => 'Create Invoice',
            'description' => 'Create a new payment invoice via Xendit',
            'security' => [['sanctum' => []]],
            'requestBody' => [
                'required' => true,
                'content' => [
                    'application/json' => [
                        'schema' => [
                            'type' => 'object',
                            'required' => ['amount', 'description'],
                            'properties' => [
                                'amount' => ['type' => 'number', 'format' => 'float', 'example' => 100.50],
                                'description' => ['type' => 'string', 'example' => 'Order payment'],
                                'customer_email' => ['type' => 'string', 'format' => 'email', 'example' => 'customer@example.com']
                            ]
                        ]
                    ]
                ]
            ],
            'responses' => [
                '201' => [
                    'description' => 'Invoice created successfully',
                    'content' => [
                        'application/json' => [
                            'schema' => ['$ref' => '#/components/schemas/ApiResponse']
                        ]
                    ]
                ]
            ]
        ]
    ],
    '/api/v1/gateway/invoice/{invoiceId}' => [
        'get' => [
            'tags' => ['Payment Gateway'],
            'summary' => 'Get Invoice',
            'description' => 'Get invoice details from Xendit',
            'security' => [['sanctum' => []]],
            'parameters' => [
                [
                    'name' => 'invoiceId',
                    'in' => 'path',
                    'description' => 'Invoice ID',
                    'required' => true,
                    'schema' => ['type' => 'string']
                ]
            ],
            'responses' => [
                '200' => [
                    'description' => 'Invoice details',
                    'content' => [
                        'application/json' => [
                            'schema' => ['$ref' => '#/components/schemas/ApiResponse']
                        ]
                    ]
                ]
            ]
        ]
    ],
    '/api/v1/gateway/invoice/{invoiceId}/expire' => [
        'post' => [
            'tags' => ['Payment Gateway'],
            'summary' => 'Expire Invoice',
            'description' => 'Expire an invoice via Xendit',
            'security' => [['sanctum' => []]],
            'parameters' => [
                [
                    'name' => 'invoiceId',
                    'in' => 'path',
                    'description' => 'Invoice ID',
                    'required' => true,
                    'schema' => ['type' => 'string']
                ]
            ],
            'responses' => [
                '200' => [
                    'description' => 'Invoice expired successfully',
                    'content' => [
                        'application/json' => [
                            'schema' => ['$ref' => '#/components/schemas/ApiResponse']
                        ]
                    ]
                ]
            ]
        ]
    ],
    '/api/v1/gateway/refund' => [
        'post' => [
            'tags' => ['Payment Gateway'],
            'summary' => 'Process Refund',
            'description' => 'Process a refund via Xendit',
            'security' => [['sanctum' => []]],
            'requestBody' => [
                'required' => true,
                'content' => [
                    'application/json' => [
                        'schema' => [
                            'type' => 'object',
                            'required' => ['payment_id', 'amount'],
                            'properties' => [
                                'payment_id' => ['type' => 'string', 'example' => 'payment_123'],
                                'amount' => ['type' => 'number', 'format' => 'float', 'example' => 50.25],
                                'reason' => ['type' => 'string', 'example' => 'Customer request']
                            ]
                        ]
                    ]
                ]
            ],
            'responses' => [
                '200' => [
                    'description' => 'Refund processed successfully',
                    'content' => [
                        'application/json' => [
                            'schema' => ['$ref' => '#/components/schemas/ApiResponse']
                        ]
                    ]
                ]
            ]
        ]
    ],

    // Webhook endpoints
    '/api/v1/webhooks/payments/{provider}' => [
        'post' => [
            'tags' => ['Webhooks'],
            'summary' => 'Payment Webhook',
            'description' => 'Handle payment webhooks from payment providers (Xendit, GCash, Maya)',
            'parameters' => [
                [
                    'name' => 'provider',
                    'in' => 'path',
                    'description' => 'Payment provider',
                    'required' => true,
                    'schema' => ['type' => 'string', 'enum' => ['xendit', 'gcash', 'maya']]
                ]
            ],
            'requestBody' => [
                'required' => true,
                'content' => [
                    'application/json' => [
                        'schema' => [
                            'type' => 'object',
                            'description' => 'Webhook payload from payment provider'
                        ]
                    ]
                ]
            ],
            'responses' => [
                '200' => [
                    'description' => 'Webhook processed successfully',
                    'content' => [
                        'application/json' => [
                            'schema' => ['$ref' => '#/components/schemas/ApiResponse']
                        ]
                    ]
                ]
            ]
        ]
    ]
];