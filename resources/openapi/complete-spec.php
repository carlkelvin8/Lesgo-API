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
    // ── CUSTOMER EXPERIENCE FEATURES ──────────────────────────────────────

    // Rating & Review System
    '/api/v1/reviews' => [
        'get' => [
            'tags' => ['Reviews & Ratings'],
            'summary' => 'List Reviews',
            'description' => 'Get reviews for drivers or services with filtering options',
            'security' => [['sanctum' => []]],
            'parameters' => [
                [
                    'name' => 'driver_id',
                    'in' => 'query',
                    'description' => 'Filter by driver ID',
                    'required' => false,
                    'schema' => ['type' => 'integer']
                ],
                [
                    'name' => 'service_id',
                    'in' => 'query',
                    'description' => 'Filter by service ID',
                    'required' => false,
                    'schema' => ['type' => 'integer']
                ],
                [
                    'name' => 'rating',
                    'in' => 'query',
                    'description' => 'Filter by rating (1-5)',
                    'required' => false,
                    'schema' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 5]
                ]
            ],
            'responses' => [
                '200' => [
                    'description' => 'List of reviews',
                    'content' => [
                        'application/json' => [
                            'schema' => ['$ref' => '#/components/schemas/PaginatedResponse']
                        ]
                    ]
                ]
            ]
        ],
        'post' => [
            'tags' => ['Reviews & Ratings'],
            'summary' => 'Create Review',
            'description' => 'Submit a review for a completed order',
            'security' => [['sanctum' => []]],
            'requestBody' => [
                'required' => true,
                'content' => [
                    'application/json' => [
                        'schema' => [
                            'type' => 'object',
                            'required' => ['order_id', 'overall_rating'],
                            'properties' => [
                                'order_id' => ['type' => 'integer', 'example' => 1],
                                'overall_rating' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 5, 'example' => 5],
                                'service_rating' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 5, 'example' => 4],
                                'driver_rating' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 5, 'example' => 5],
                                'delivery_time_rating' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 5, 'example' => 4],
                                'communication_rating' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 5, 'example' => 5],
                                'professionalism_rating' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 5, 'example' => 5],
                                'review_title' => ['type' => 'string', 'example' => 'Excellent service!'],
                                'review_comment' => ['type' => 'string', 'example' => 'The driver was very professional and delivered on time.'],
                                'review_tags' => ['type' => 'array', 'items' => ['type' => 'string'], 'example' => ['fast', 'professional', 'friendly']],
                                'review_images' => ['type' => 'array', 'items' => ['type' => 'string', 'format' => 'url']],
                                'is_anonymous' => ['type' => 'boolean', 'example' => false]
                            ]
                        ]
                    ]
                ]
            ],
            'responses' => [
                '201' => [
                    'description' => 'Review created successfully',
                    'content' => [
                        'application/json' => [
                            'schema' => ['$ref' => '#/components/schemas/ApiResponse']
                        ]
                    ]
                ]
            ]
        ]
    ],
    '/api/v1/reviews/my-reviews' => [
        'get' => [
            'tags' => ['Reviews & Ratings'],
            'summary' => 'Get My Reviews',
            'description' => 'Get all reviews submitted by the authenticated user',
            'security' => [['sanctum' => []]],
            'responses' => [
                '200' => [
                    'description' => 'User reviews retrieved successfully',
                    'content' => [
                        'application/json' => [
                            'schema' => ['$ref' => '#/components/schemas/PaginatedResponse']
                        ]
                    ]
                ]
            ]
        ]
    ],
    '/api/v1/reviews/statistics' => [
        'get' => [
            'tags' => ['Reviews & Ratings'],
            'summary' => 'Get Rating Statistics',
            'description' => 'Get average ratings and distribution for drivers or services',
            'security' => [['sanctum' => []]],
            'parameters' => [
                [
                    'name' => 'driver_id',
                    'in' => 'query',
                    'description' => 'Get statistics for specific driver',
                    'required' => false,
                    'schema' => ['type' => 'integer']
                ],
                [
                    'name' => 'service_id',
                    'in' => 'query',
                    'description' => 'Get statistics for specific service',
                    'required' => false,
                    'schema' => ['type' => 'integer']
                ]
            ],
            'responses' => [
                '200' => [
                    'description' => 'Rating statistics retrieved successfully',
                    'content' => [
                        'application/json' => [
                            'schema' => ['$ref' => '#/components/schemas/ApiResponse']
                        ]
                    ]
                ]
            ]
        ]
    ],

    // Support Ticket System
    '/api/v1/support/tickets' => [
        'get' => [
            'tags' => ['Customer Support'],
            'summary' => 'List Support Tickets',
            'description' => 'Get user support tickets with filtering options',
            'security' => [['sanctum' => []]],
            'parameters' => [
                [
                    'name' => 'status',
                    'in' => 'query',
                    'description' => 'Filter by ticket status',
                    'required' => false,
                    'schema' => ['type' => 'string', 'enum' => ['open', 'in_progress', 'waiting_customer', 'waiting_internal', 'resolved', 'closed', 'cancelled']]
                ],
                [
                    'name' => 'category',
                    'in' => 'query',
                    'description' => 'Filter by ticket category',
                    'required' => false,
                    'schema' => ['type' => 'string', 'enum' => ['order_issue', 'payment_issue', 'driver_complaint', 'app_bug', 'feature_request', 'account_issue', 'refund_request', 'general_inquiry', 'other']]
                ]
            ],
            'responses' => [
                '200' => [
                    'description' => 'Support tickets retrieved successfully',
                    'content' => [
                        'application/json' => [
                            'schema' => ['$ref' => '#/components/schemas/PaginatedResponse']
                        ]
                    ]
                ]
            ]
        ],
        'post' => [
            'tags' => ['Customer Support'],
            'summary' => 'Create Support Ticket',
            'description' => 'Create a new support ticket',
            'security' => [['sanctum' => []]],
            'requestBody' => [
                'required' => true,
                'content' => [
                    'application/json' => [
                        'schema' => [
                            'type' => 'object',
                            'required' => ['subject', 'description', 'category'],
                            'properties' => [
                                'subject' => ['type' => 'string', 'example' => 'Issue with my order delivery'],
                                'description' => ['type' => 'string', 'example' => 'My order was not delivered to the correct address and I need assistance.'],
                                'category' => ['type' => 'string', 'enum' => ['order_issue', 'payment_issue', 'driver_complaint', 'app_bug', 'feature_request', 'account_issue', 'refund_request', 'general_inquiry', 'other'], 'example' => 'order_issue'],
                                'priority' => ['type' => 'string', 'enum' => ['low', 'medium', 'high', 'urgent'], 'example' => 'medium'],
                                'order_id' => ['type' => 'integer', 'example' => 123],
                                'attachments' => ['type' => 'array', 'items' => ['type' => 'string', 'format' => 'url']],
                                'metadata' => ['type' => 'object']
                            ]
                        ]
                    ]
                ]
            ],
            'responses' => [
                '201' => [
                    'description' => 'Support ticket created successfully',
                    'content' => [
                        'application/json' => [
                            'schema' => ['$ref' => '#/components/schemas/ApiResponse']
                        ]
                    ]
                ]
            ]
        ]
    ],
    '/api/v1/support/tickets/{ticket}' => [
        'get' => [
            'tags' => ['Customer Support'],
            'summary' => 'Get Support Ticket',
            'description' => 'Get details of a specific support ticket with messages',
            'security' => [['sanctum' => []]],
            'parameters' => [
                [
                    'name' => 'ticket',
                    'in' => 'path',
                    'description' => 'Support Ticket ID',
                    'required' => true,
                    'schema' => ['type' => 'integer']
                ]
            ],
            'responses' => [
                '200' => [
                    'description' => 'Support ticket retrieved successfully',
                    'content' => [
                        'application/json' => [
                            'schema' => ['$ref' => '#/components/schemas/ApiResponse']
                        ]
                    ]
                ]
            ]
        ]
    ],
    '/api/v1/support/tickets/{ticket}/messages' => [
        'post' => [
            'tags' => ['Customer Support'],
            'summary' => 'Add Message to Ticket',
            'description' => 'Add a message to an existing support ticket',
            'security' => [['sanctum' => []]],
            'parameters' => [
                [
                    'name' => 'ticket',
                    'in' => 'path',
                    'description' => 'Support Ticket ID',
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
                            'required' => ['message'],
                            'properties' => [
                                'message' => ['type' => 'string', 'example' => 'Thank you for the quick response. The issue has been resolved.'],
                                'attachments' => ['type' => 'array', 'items' => ['type' => 'string', 'format' => 'url']]
                            ]
                        ]
                    ]
                ]
            ],
            'responses' => [
                '201' => [
                    'description' => 'Message added successfully',
                    'content' => [
                        'application/json' => [
                            'schema' => ['$ref' => '#/components/schemas/ApiResponse']
                        ]
                    ]
                ]
            ]
        ]
    ],

    // FAQ & Help Center
    '/api/v1/faq/categories' => [
        'get' => [
            'tags' => ['FAQ & Help'],
            'summary' => 'List FAQ Categories',
            'description' => 'Get all FAQ categories with their articles',
            'responses' => [
                '200' => [
                    'description' => 'FAQ categories retrieved successfully',
                    'content' => [
                        'application/json' => [
                            'schema' => ['$ref' => '#/components/schemas/ApiResponse']
                        ]
                    ]
                ]
            ]
        ]
    ],
    '/api/v1/faq/search' => [
        'get' => [
            'tags' => ['FAQ & Help'],
            'summary' => 'Search FAQ Articles',
            'description' => 'Search FAQ articles by keywords',
            'parameters' => [
                [
                    'name' => 'q',
                    'in' => 'query',
                    'description' => 'Search query',
                    'required' => true,
                    'schema' => ['type' => 'string', 'example' => 'payment issue']
                ],
                [
                    'name' => 'category_id',
                    'in' => 'query',
                    'description' => 'Filter by category',
                    'required' => false,
                    'schema' => ['type' => 'integer']
                ]
            ],
            'responses' => [
                '200' => [
                    'description' => 'Search results retrieved successfully',
                    'content' => [
                        'application/json' => [
                            'schema' => ['$ref' => '#/components/schemas/PaginatedResponse']
                        ]
                    ]
                ]
            ]
        ]
    ],
    '/api/v1/faq/featured' => [
        'get' => [
            'tags' => ['FAQ & Help'],
            'summary' => 'Get Featured Articles',
            'description' => 'Get featured FAQ articles',
            'responses' => [
                '200' => [
                    'description' => 'Featured articles retrieved successfully',
                    'content' => [
                        'application/json' => [
                            'schema' => ['$ref' => '#/components/schemas/ApiResponse']
                        ]
                    ]
                ]
            ]
        ]
    ],

    // Live Order Tracking
    '/api/v1/tracking/orders/{order}' => [
        'get' => [
            'tags' => ['Order Tracking'],
            'summary' => 'Track Order',
            'description' => 'Get real-time tracking information for an order',
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
                    'description' => 'Order tracking retrieved successfully',
                    'content' => [
                        'application/json' => [
                            'schema' => ['$ref' => '#/components/schemas/ApiResponse']
                        ]
                    ]
                ]
            ]
        ]
    ],
    '/api/v1/tracking/orders/{order}/location' => [
        'get' => [
            'tags' => ['Order Tracking'],
            'summary' => 'Get Live Driver Location',
            'description' => 'Get real-time location of the driver for an order',
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
                    'description' => 'Live location retrieved successfully',
                    'content' => [
                        'application/json' => [
                            'schema' => ['$ref' => '#/components/schemas/ApiResponse']
                        ]
                    ]
                ]
            ]
        ]
    ],
    '/api/v1/tracking/orders/{order}/events' => [
        'post' => [
            'tags' => ['Order Tracking'],
            'summary' => 'Add Tracking Event',
            'description' => 'Add a new tracking event to an order (drivers/system only)',
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
                            'required' => ['event_type', 'event_title'],
                            'properties' => [
                                'event_type' => ['type' => 'string', 'example' => 'driver_en_route'],
                                'event_title' => ['type' => 'string', 'example' => 'Driver En Route'],
                                'event_description' => ['type' => 'string', 'example' => 'Driver is on the way to pickup location'],
                                'event_category' => ['type' => 'string', 'enum' => ['order', 'payment', 'delivery', 'system'], 'example' => 'delivery'],
                                'latitude' => ['type' => 'number', 'format' => 'float', 'example' => 14.5995],
                                'longitude' => ['type' => 'number', 'format' => 'float', 'example' => 120.9842],
                                'location_address' => ['type' => 'string', 'example' => '123 Main St, Manila'],
                                'attachments' => ['type' => 'array', 'items' => ['type' => 'string', 'format' => 'url']],
                                'metadata' => ['type' => 'object'],
                                'is_milestone' => ['type' => 'boolean', 'example' => false]
                            ]
                        ]
                    ]
                ]
            ],
            'responses' => [
                '201' => [
                    'description' => 'Tracking event added successfully',
                    'content' => [
                        'application/json' => [
                            'schema' => ['$ref' => '#/components/schemas/ApiResponse']
                        ]
                    ]
                ]
            ]
        ]
    ],
    '/api/v1/tracking/orders/multiple' => [
        'post' => [
            'tags' => ['Order Tracking'],
            'summary' => 'Track Multiple Orders',
            'description' => 'Get tracking summary for multiple orders',
            'security' => [['sanctum' => []]],
            'requestBody' => [
                'required' => true,
                'content' => [
                    'application/json' => [
                        'schema' => [
                            'type' => 'object',
                            'required' => ['order_ids'],
                            'properties' => [
                                'order_ids' => ['type' => 'array', 'items' => ['type' => 'integer'], 'example' => [1, 2, 3]]
                            ]
                        ]
                    ]
                ]
            ],
            'responses' => [
                '200' => [
                    'description' => 'Multiple order tracking retrieved successfully',
                    'content' => [
                        'application/json' => [
                            'schema' => ['$ref' => '#/components/schemas/ApiResponse']
                        ]
                    ]
                ]
            ]
        ]
    ],

    // ── DOCUMENT VERIFICATION SYSTEM ──────────────────────────────────────

    // Document Submission Endpoints
    '/api/v1/documents/submit' => [
        'post' => [
            'tags' => ['Document Verification'],
            'summary' => 'Submit Document for Verification',
            'description' => 'Submit documents for admin verification (driver license, business permit, etc.)',
            'security' => [['sanctum' => []]],
            'requestBody' => [
                'required' => true,
                'content' => [
                    'application/json' => [
                        'schema' => [
                            'type' => 'object',
                            'required' => ['document_type', 'document_urls'],
                            'properties' => [
                                'document_type' => ['type' => 'string', 'enum' => ['driver_license', 'vehicle_registration', 'vehicle_insurance', 'business_permit', 'bir_certificate', 'valid_id', 'proof_of_address', 'medical_certificate', 'police_clearance', 'barangay_clearance', 'other'], 'example' => 'driver_license'],
                                'document_number' => ['type' => 'string', 'example' => 'N01-12-123456'],
                                'document_urls' => ['type' => 'array', 'items' => ['type' => 'string', 'format' => 'url'], 'example' => ['https://example.com/license-front.jpg', 'https://example.com/license-back.jpg']],
                                'description' => ['type' => 'string', 'example' => 'Driver license for verification'],
                                'expires_at' => ['type' => 'string', 'format' => 'date', 'example' => '2027-12-31'],
                                'metadata' => ['type' => 'object']
                            ]
                        ]
                    ]
                ]
            ],
            'responses' => [
                '201' => [
                    'description' => 'Document submitted successfully',
                    'content' => [
                        'application/json' => [
                            'schema' => ['$ref' => '#/components/schemas/ApiResponse']
                        ]
                    ]
                ]
            ]
        ]
    ],
    '/api/v1/documents/my-documents' => [
        'get' => [
            'tags' => ['Document Verification'],
            'summary' => 'Get My Submitted Documents',
            'description' => 'Get all documents submitted by the authenticated user',
            'security' => [['sanctum' => []]],
            'parameters' => [
                [
                    'name' => 'status',
                    'in' => 'query',
                    'description' => 'Filter by document status',
                    'required' => false,
                    'schema' => ['type' => 'string', 'enum' => ['pending', 'under_review', 'approved', 'rejected', 'expired']]
                ],
                [
                    'name' => 'document_type',
                    'in' => 'query',
                    'description' => 'Filter by document type',
                    'required' => false,
                    'schema' => ['type' => 'string']
                ]
            ],
            'responses' => [
                '200' => [
                    'description' => 'Documents retrieved successfully',
                    'content' => [
                        'application/json' => [
                            'schema' => ['$ref' => '#/components/schemas/PaginatedResponse']
                        ]
                    ]
                ]
            ]
        ]
    ],
    '/api/v1/documents/types' => [
        'get' => [
            'tags' => ['Document Verification'],
            'summary' => 'Get Document Types and Requirements',
            'description' => 'Get available document types and their submission requirements',
            'responses' => [
                '200' => [
                    'description' => 'Document types retrieved successfully',
                    'content' => [
                        'application/json' => [
                            'schema' => ['$ref' => '#/components/schemas/ApiResponse']
                        ]
                    ]
                ]
            ]
        ]
    ],
    '/api/v1/documents/verification-status' => [
        'get' => [
            'tags' => ['Document Verification'],
            'summary' => 'Get Verification Status',
            'description' => 'Get overall document verification status for the user',
            'security' => [['sanctum' => []]],
            'responses' => [
                '200' => [
                    'description' => 'Verification status retrieved successfully',
                    'content' => [
                        'application/json' => [
                            'schema' => ['$ref' => '#/components/schemas/ApiResponse']
                        ]
                    ]
                ]
            ]
        ]
    ],

    // Admin Document Verification Endpoints
    '/api/v1/admin/documents' => [
        'get' => [
            'tags' => ['Admin - Document Verification'],
            'summary' => 'List All Document Verifications',
            'description' => 'Get all document verifications for admin review (admin only)',
            'security' => [['sanctum' => []]],
            'parameters' => [
                [
                    'name' => 'status',
                    'in' => 'query',
                    'description' => 'Filter by status',
                    'required' => false,
                    'schema' => ['type' => 'string', 'enum' => ['pending', 'under_review', 'approved', 'rejected', 'expired']]
                ],
                [
                    'name' => 'document_type',
                    'in' => 'query',
                    'description' => 'Filter by document type',
                    'required' => false,
                    'schema' => ['type' => 'string']
                ],
                [
                    'name' => 'user_id',
                    'in' => 'query',
                    'description' => 'Filter by user ID',
                    'required' => false,
                    'schema' => ['type' => 'integer']
                ]
            ],
            'responses' => [
                '200' => [
                    'description' => 'Document verifications retrieved successfully',
                    'content' => [
                        'application/json' => [
                            'schema' => ['$ref' => '#/components/schemas/PaginatedResponse']
                        ]
                    ]
                ]
            ]
        ]
    ],
    '/api/v1/admin/documents/statistics' => [
        'get' => [
            'tags' => ['Admin - Document Verification'],
            'summary' => 'Get Document Verification Statistics',
            'description' => 'Get statistics for admin dashboard (admin only)',
            'security' => [['sanctum' => []]],
            'responses' => [
                '200' => [
                    'description' => 'Statistics retrieved successfully',
                    'content' => [
                        'application/json' => [
                            'schema' => ['$ref' => '#/components/schemas/ApiResponse']
                        ]
                    ]
                ]
            ]
        ]
    ],
    '/api/v1/admin/documents/{document}/approve' => [
        'post' => [
            'tags' => ['Admin - Document Verification'],
            'summary' => 'Approve Document',
            'description' => 'Approve a document verification (admin only)',
            'security' => [['sanctum' => []]],
            'parameters' => [
                [
                    'name' => 'document',
                    'in' => 'path',
                    'description' => 'Document Verification ID',
                    'required' => true,
                    'schema' => ['type' => 'integer']
                ]
            ],
            'requestBody' => [
                'required' => false,
                'content' => [
                    'application/json' => [
                        'schema' => [
                            'type' => 'object',
                            'properties' => [
                                'admin_notes' => ['type' => 'string', 'example' => 'Document verified and approved'],
                                'expires_at' => ['type' => 'string', 'format' => 'date', 'example' => '2027-12-31']
                            ]
                        ]
                    ]
                ]
            ],
            'responses' => [
                '200' => [
                    'description' => 'Document approved successfully',
                    'content' => [
                        'application/json' => [
                            'schema' => ['$ref' => '#/components/schemas/ApiResponse']
                        ]
                    ]
                ]
            ]
        ]
    ],
    '/api/v1/admin/documents/{document}/reject' => [
        'post' => [
            'tags' => ['Admin - Document Verification'],
            'summary' => 'Reject Document',
            'description' => 'Reject a document verification (admin only)',
            'security' => [['sanctum' => []]],
            'parameters' => [
                [
                    'name' => 'document',
                    'in' => 'path',
                    'description' => 'Document Verification ID',
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
                            'required' => ['rejection_reason'],
                            'properties' => [
                                'rejection_reason' => ['type' => 'string', 'example' => 'Document is not clear or expired'],
                                'admin_notes' => ['type' => 'string', 'example' => 'Please resubmit with clearer images']
                            ]
                        ]
                    ]
                ]
            ],
            'responses' => [
                '200' => [
                    'description' => 'Document rejected successfully',
                    'content' => [
                        'application/json' => [
                            'schema' => ['$ref' => '#/components/schemas/ApiResponse']
                        ]
                    ]
                ]
            ]
        ]
    ],

    // ── SOCIAL MEDIA INTEGRATION ──────────────────────────────────────────

    // Social Media Platforms
    '/api/v1/social/platforms' => [
        'get' => [
            'tags' => ['Social Media'],
            'summary' => 'Get Supported Platforms',
            'description' => 'Get list of supported social media platforms and their configurations',
            'responses' => [
                '200' => [
                    'description' => 'Supported platforms retrieved successfully',
                    'content' => [
                        'application/json' => [
                            'schema' => ['$ref' => '#/components/schemas/ApiResponse']
                        ]
                    ]
                ]
            ]
        ]
    ],
    '/api/v1/social/platforms/{platform}/guidelines' => [
        'get' => [
            'tags' => ['Social Media'],
            'summary' => 'Get Platform Guidelines',
            'description' => 'Get sharing guidelines and best practices for a specific platform',
            'parameters' => [
                [
                    'name' => 'platform',
                    'in' => 'path',
                    'description' => 'Social media platform',
                    'required' => true,
                    'schema' => ['type' => 'string', 'enum' => ['facebook', 'twitter', 'instagram', 'linkedin', 'whatsapp', 'telegram']]
                ]
            ],
            'responses' => [
                '200' => [
                    'description' => 'Platform guidelines retrieved successfully',
                    'content' => [
                        'application/json' => [
                            'schema' => ['$ref' => '#/components/schemas/ApiResponse']
                        ]
                    ]
                ]
            ]
        ]
    ],

    // Order Sharing
    '/api/v1/social/orders/{order}/share' => [
        'post' => [
            'tags' => ['Social Media'],
            'summary' => 'Share Order on Social Media',
            'description' => 'Generate shareable content for completed orders or service reviews',
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
                            'required' => ['platform', 'share_type'],
                            'properties' => [
                                'platform' => ['type' => 'string', 'enum' => ['facebook', 'twitter', 'instagram', 'linkedin', 'whatsapp', 'telegram'], 'example' => 'facebook'],
                                'share_type' => ['type' => 'string', 'enum' => ['order_completed', 'service_review'], 'example' => 'order_completed'],
                                'rating' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 5, 'example' => 5, 'description' => 'Required for service_review type']
                            ]
                        ]
                    ]
                ]
            ],
            'responses' => [
                '201' => [
                    'description' => 'Share content generated successfully',
                    'content' => [
                        'application/json' => [
                            'schema' => ['$ref' => '#/components/schemas/ApiResponse']
                        ]
                    ]
                ]
            ]
        ]
    ],

    // Referral Sharing
    '/api/v1/social/referral/share' => [
        'post' => [
            'tags' => ['Social Media'],
            'summary' => 'Share Referral Invitation',
            'description' => 'Generate shareable referral content to invite friends',
            'security' => [['sanctum' => []]],
            'requestBody' => [
                'required' => true,
                'content' => [
                    'application/json' => [
                        'schema' => [
                            'type' => 'object',
                            'required' => ['platform'],
                            'properties' => [
                                'platform' => ['type' => 'string', 'enum' => ['facebook', 'twitter', 'instagram', 'linkedin', 'whatsapp', 'telegram'], 'example' => 'whatsapp']
                            ]
                        ]
                    ]
                ]
            ],
            'responses' => [
                '201' => [
                    'description' => 'Referral share content generated successfully',
                    'content' => [
                        'application/json' => [
                            'schema' => ['$ref' => '#/components/schemas/ApiResponse']
                        ]
                    ]
                ]
            ]
        ]
    ],

    // Analytics
    '/api/v1/social/analytics' => [
        'get' => [
            'tags' => ['Social Media'],
            'summary' => 'Get Sharing Analytics',
            'description' => 'Get social media sharing analytics for the authenticated user',
            'security' => [['sanctum' => []]],
            'responses' => [
                '200' => [
                    'description' => 'Sharing analytics retrieved successfully',
                    'content' => [
                        'application/json' => [
                            'schema' => ['$ref' => '#/components/schemas/ApiResponse']
                        ]
                    ]
                ]
            ]
        ]
    ],

    // ── GEOFENCING SYSTEM ──────────────────────────────────────────────────

    // Geofencing Management
    '/api/v1/geofences' => [
        'get' => [
            'tags' => ['Geofencing'],
            'summary' => 'List Geofences',
            'description' => 'Returns a paginated list of geofences',
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
                    'name' => 'type',
                    'in' => 'query',
                    'description' => 'Filter by geofence type',
                    'required' => false,
                    'schema' => ['type' => 'string', 'enum' => ['delivery_zone', 'service_area', 'restricted_area', 'pickup_zone', 'partner_location']]
                ],
                [
                    'name' => 'is_active',
                    'in' => 'query',
                    'description' => 'Filter by active status',
                    'required' => false,
                    'schema' => ['type' => 'boolean']
                ]
            ],
            'responses' => [
                '200' => [
                    'description' => 'List of geofences',
                    'content' => [
                        'application/json' => [
                            'schema' => ['$ref' => '#/components/schemas/PaginatedResponse']
                        ]
                    ]
                ]
            ]
        ],
        'post' => [
            'tags' => ['Geofencing'],
            'summary' => 'Create Geofence',
            'description' => 'Create a new geofence with automatic notifications',
            'security' => [['sanctum' => []]],
            'requestBody' => [
                'required' => true,
                'content' => [
                    'application/json' => [
                        'schema' => [
                            'type' => 'object',
                            'required' => ['name', 'type', 'shape'],
                            'properties' => [
                                'name' => ['type' => 'string', 'example' => 'Downtown Delivery Zone'],
                                'description' => ['type' => 'string', 'example' => 'Main delivery area for downtown orders'],
                                'type' => ['type' => 'string', 'enum' => ['delivery_zone', 'service_area', 'restricted_area', 'pickup_zone', 'partner_location'], 'example' => 'delivery_zone'],
                                'shape' => ['type' => 'string', 'enum' => ['circle', 'polygon'], 'example' => 'circle'],
                                'center_latitude' => ['type' => 'number', 'format' => 'float', 'example' => 14.5995],
                                'center_longitude' => ['type' => 'number', 'format' => 'float', 'example' => 120.9842],
                                'radius_meters' => ['type' => 'integer', 'example' => 1000],
                                'polygon_coordinates' => [
                                    'type' => 'array',
                                    'items' => [
                                        'type' => 'array',
                                        'items' => ['type' => 'number'],
                                        'minItems' => 2,
                                        'maxItems' => 2
                                    ],
                                    'example' => [[120.9842, 14.5995], [120.9852, 14.6005], [120.9862, 14.5985]]
                                ],
                                'trigger_on_enter' => ['type' => 'boolean', 'example' => true],
                                'trigger_on_exit' => ['type' => 'boolean', 'example' => true],
                                'trigger_on_dwell' => ['type' => 'boolean', 'example' => false],
                                'dwell_time_minutes' => ['type' => 'integer', 'example' => 5],
                                'notification_types' => [
                                    'type' => 'array',
                                    'items' => ['type' => 'string', 'enum' => ['push', 'sms', 'email', 'webhook']],
                                    'example' => ['push', 'webhook']
                                ],
                                'webhook_url' => ['type' => 'string', 'format' => 'url', 'example' => 'https://api.example.com/geofence-webhook'],
                                'is_active' => ['type' => 'boolean', 'example' => true],
                                'metadata' => ['type' => 'object', 'example' => ['priority' => 'high', 'zone_code' => 'DZ001']]
                            ]
                        ]
                    ]
                ]
            ],
            'responses' => [
                '201' => [
                    'description' => 'Geofence created successfully',
                    'content' => [
                        'application/json' => [
                            'schema' => ['$ref' => '#/components/schemas/ApiResponse']
                        ]
                    ]
                ]
            ]
        ]
    ],
    '/api/v1/geofences/types' => [
        'get' => [
            'tags' => ['Geofencing'],
            'summary' => 'Get Geofence Types',
            'description' => 'Returns available geofence types and their descriptions',
            'security' => [['sanctum' => []]],
            'responses' => [
                '200' => [
                    'description' => 'List of geofence types',
                    'content' => [
                        'application/json' => [
                            'schema' => ['$ref' => '#/components/schemas/ApiResponse']
                        ]
                    ]
                ]
            ]
        ]
    ],
    '/api/v1/geofences/nearby' => [
        'get' => [
            'tags' => ['Geofencing'],
            'summary' => 'Find Nearby Geofences',
            'description' => 'Find geofences near a specific location',
            'security' => [['sanctum' => []]],
            'parameters' => [
                [
                    'name' => 'latitude',
                    'in' => 'query',
                    'description' => 'Latitude coordinate',
                    'required' => true,
                    'schema' => ['type' => 'number', 'format' => 'float']
                ],
                [
                    'name' => 'longitude',
                    'in' => 'query',
                    'description' => 'Longitude coordinate',
                    'required' => true,
                    'schema' => ['type' => 'number', 'format' => 'float']
                ],
                [
                    'name' => 'radius_km',
                    'in' => 'query',
                    'description' => 'Search radius in kilometers',
                    'required' => false,
                    'schema' => ['type' => 'number', 'default' => 5]
                ],
                [
                    'name' => 'type',
                    'in' => 'query',
                    'description' => 'Filter by geofence type',
                    'required' => false,
                    'schema' => ['type' => 'string', 'enum' => ['delivery_zone', 'service_area', 'restricted_area', 'pickup_zone', 'partner_location']]
                ]
            ],
            'responses' => [
                '200' => [
                    'description' => 'List of nearby geofences',
                    'content' => [
                        'application/json' => [
                            'schema' => ['$ref' => '#/components/schemas/ApiResponse']
                        ]
                    ]
                ]
            ]
        ]
    ],
    '/api/v1/geofences/statistics' => [
        'get' => [
            'tags' => ['Geofencing'],
            'summary' => 'Get Geofencing Statistics',
            'description' => 'Returns geofencing analytics and statistics',
            'security' => [['sanctum' => []]],
            'parameters' => [
                [
                    'name' => 'period',
                    'in' => 'query',
                    'description' => 'Time period for statistics',
                    'required' => false,
                    'schema' => ['type' => 'string', 'enum' => ['today', 'week', 'month', 'year'], 'default' => 'week']
                ],
                [
                    'name' => 'geofence_id',
                    'in' => 'query',
                    'description' => 'Filter by specific geofence',
                    'required' => false,
                    'schema' => ['type' => 'integer']
                ]
            ],
            'responses' => [
                '200' => [
                    'description' => 'Geofencing statistics',
                    'content' => [
                        'application/json' => [
                            'schema' => ['$ref' => '#/components/schemas/ApiResponse']
                        ]
                    ]
                ]
            ]
        ]
    ],
    '/api/v1/geofences/{geofence}' => [
        'get' => [
            'tags' => ['Geofencing'],
            'summary' => 'Get Geofence Details',
            'description' => 'Returns details of a specific geofence',
            'security' => [['sanctum' => []]],
            'parameters' => [
                [
                    'name' => 'geofence',
                    'in' => 'path',
                    'description' => 'Geofence ID',
                    'required' => true,
                    'schema' => ['type' => 'integer']
                ]
            ],
            'responses' => [
                '200' => [
                    'description' => 'Geofence details',
                    'content' => [
                        'application/json' => [
                            'schema' => ['$ref' => '#/components/schemas/ApiResponse']
                        ]
                    ]
                ],
                '404' => [
                    'description' => 'Geofence not found',
                    'content' => [
                        'application/json' => [
                            'schema' => ['$ref' => '#/components/schemas/ErrorResponse']
                        ]
                    ]
                ]
            ]
        ],
        'put' => [
            'tags' => ['Geofencing'],
            'summary' => 'Update Geofence',
            'description' => 'Update an existing geofence',
            'security' => [['sanctum' => []]],
            'parameters' => [
                [
                    'name' => 'geofence',
                    'in' => 'path',
                    'description' => 'Geofence ID',
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
                                'name' => ['type' => 'string', 'example' => 'Updated Delivery Zone'],
                                'description' => ['type' => 'string', 'example' => 'Updated description'],
                                'type' => ['type' => 'string', 'enum' => ['delivery_zone', 'service_area', 'restricted_area', 'pickup_zone', 'partner_location']],
                                'shape' => ['type' => 'string', 'enum' => ['circle', 'polygon']],
                                'center_latitude' => ['type' => 'number', 'format' => 'float'],
                                'center_longitude' => ['type' => 'number', 'format' => 'float'],
                                'radius_meters' => ['type' => 'integer'],
                                'polygon_coordinates' => [
                                    'type' => 'array',
                                    'items' => [
                                        'type' => 'array',
                                        'items' => ['type' => 'number'],
                                        'minItems' => 2,
                                        'maxItems' => 2
                                    ]
                                ],
                                'trigger_on_enter' => ['type' => 'boolean'],
                                'trigger_on_exit' => ['type' => 'boolean'],
                                'trigger_on_dwell' => ['type' => 'boolean'],
                                'dwell_time_minutes' => ['type' => 'integer'],
                                'notification_types' => [
                                    'type' => 'array',
                                    'items' => ['type' => 'string', 'enum' => ['push', 'sms', 'email', 'webhook']]
                                ],
                                'webhook_url' => ['type' => 'string', 'format' => 'url'],
                                'is_active' => ['type' => 'boolean'],
                                'metadata' => ['type' => 'object']
                            ]
                        ]
                    ]
                ]
            ],
            'responses' => [
                '200' => [
                    'description' => 'Geofence updated successfully',
                    'content' => [
                        'application/json' => [
                            'schema' => ['$ref' => '#/components/schemas/ApiResponse']
                        ]
                    ]
                ]
            ]
        ],
        'delete' => [
            'tags' => ['Geofencing'],
            'summary' => 'Delete Geofence',
            'description' => 'Delete a geofence',
            'security' => [['sanctum' => []]],
            'parameters' => [
                [
                    'name' => 'geofence',
                    'in' => 'path',
                    'description' => 'Geofence ID',
                    'required' => true,
                    'schema' => ['type' => 'integer']
                ]
            ],
            'responses' => [
                '200' => [
                    'description' => 'Geofence deleted successfully',
                    'content' => [
                        'application/json' => [
                            'schema' => ['$ref' => '#/components/schemas/ApiResponse']
                        ]
                    ]
                ]
            ]
        ]
    ],
    '/api/v1/geofences/{geofence}/toggle' => [
        'post' => [
            'tags' => ['Geofencing'],
            'summary' => 'Toggle Geofence Status',
            'description' => 'Toggle geofence active/inactive status',
            'security' => [['sanctum' => []]],
            'parameters' => [
                [
                    'name' => 'geofence',
                    'in' => 'path',
                    'description' => 'Geofence ID',
                    'required' => true,
                    'schema' => ['type' => 'integer']
                ]
            ],
            'responses' => [
                '200' => [
                    'description' => 'Geofence status toggled successfully',
                    'content' => [
                        'application/json' => [
                            'schema' => ['$ref' => '#/components/schemas/ApiResponse']
                        ]
                    ]
                ]
            ]
        ]
    ],
    '/api/v1/geofences/{geofence}/events' => [
        'get' => [
            'tags' => ['Geofencing'],
            'summary' => 'Get Geofence Events',
            'description' => 'Returns events for a specific geofence',
            'security' => [['sanctum' => []]],
            'parameters' => [
                [
                    'name' => 'geofence',
                    'in' => 'path',
                    'description' => 'Geofence ID',
                    'required' => true,
                    'schema' => ['type' => 'integer']
                ],
                [
                    'name' => 'page',
                    'in' => 'query',
                    'description' => 'Page number',
                    'required' => false,
                    'schema' => ['type' => 'integer', 'default' => 1]
                ],
                [
                    'name' => 'event_type',
                    'in' => 'query',
                    'description' => 'Filter by event type',
                    'required' => false,
                    'schema' => ['type' => 'string', 'enum' => ['enter', 'exit', 'dwell']]
                ],
                [
                    'name' => 'from_date',
                    'in' => 'query',
                    'description' => 'Filter events from date',
                    'required' => false,
                    'schema' => ['type' => 'string', 'format' => 'date']
                ],
                [
                    'name' => 'to_date',
                    'in' => 'query',
                    'description' => 'Filter events to date',
                    'required' => false,
                    'schema' => ['type' => 'string', 'format' => 'date']
                ]
            ],
            'responses' => [
                '200' => [
                    'description' => 'List of geofence events',
                    'content' => [
                        'application/json' => [
                            'schema' => ['$ref' => '#/components/schemas/PaginatedResponse']
                        ]
                    ]
                ]
            ]
        ]
    ],
    '/api/v1/geofences/location/check' => [
        'post' => [
            'tags' => ['Geofencing'],
            'summary' => 'Check Location Against Geofences',
            'description' => 'Check if a location is within any geofences',
            'security' => [['sanctum' => []]],
            'requestBody' => [
                'required' => true,
                'content' => [
                    'application/json' => [
                        'schema' => [
                            'type' => 'object',
                            'required' => ['latitude', 'longitude'],
                            'properties' => [
                                'latitude' => ['type' => 'number', 'format' => 'float', 'example' => 14.5995],
                                'longitude' => ['type' => 'number', 'format' => 'float', 'example' => 120.9842],
                                'accuracy' => ['type' => 'number', 'example' => 10.5],
                                'timestamp' => ['type' => 'string', 'format' => 'date-time', 'example' => '2026-04-09T12:00:00Z'],
                                'order_id' => ['type' => 'integer', 'example' => 123],
                                'metadata' => ['type' => 'object', 'example' => ['device_id' => 'mobile_123']]
                            ]
                        ]
                    ]
                ]
            ],
            'responses' => [
                '200' => [
                    'description' => 'Location check results',
                    'content' => [
                        'application/json' => [
                            'schema' => ['$ref' => '#/components/schemas/ApiResponse']
                        ]
                    ]
                ]
            ]
        ]
    ],
    '/api/v1/geofences/location/process' => [
        'post' => [
            'tags' => ['Geofencing'],
            'summary' => 'Process Location Update',
            'description' => 'Process location update and trigger geofence events',
            'security' => [['sanctum' => []]],
            'requestBody' => [
                'required' => true,
                'content' => [
                    'application/json' => [
                        'schema' => [
                            'type' => 'object',
                            'required' => ['latitude', 'longitude'],
                            'properties' => [
                                'latitude' => ['type' => 'number', 'format' => 'float', 'example' => 14.5995],
                                'longitude' => ['type' => 'number', 'format' => 'float', 'example' => 120.9842],
                                'accuracy' => ['type' => 'number', 'example' => 10.5],
                                'timestamp' => ['type' => 'string', 'format' => 'date-time', 'example' => '2026-04-09T12:00:00Z'],
                                'order_id' => ['type' => 'integer', 'example' => 123],
                                'metadata' => ['type' => 'object', 'example' => ['device_id' => 'mobile_123', 'speed' => 25.5]]
                            ]
                        ]
                    ]
                ]
            ],
            'responses' => [
                '200' => [
                    'description' => 'Location processed successfully',
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