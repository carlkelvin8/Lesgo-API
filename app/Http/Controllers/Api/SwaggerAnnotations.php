<?php

/**
 * @OA\Info(
 *     title="LeSGo API",
 *     version="1.0.0",
 *     description="Logistics & multi-service API for LeSGo — ride, delivery, buy, and eat services.",
 *     @OA\Contact(email="support@lesgo.app")
 * )
 *
 * @OA\Server(url=L5_SWAGGER_CONST_HOST, description="API Server")
 *
 * @OA\SecurityScheme(
 *     securityScheme="sanctum",
 *     type="http",
 *     scheme="bearer",
 *     bearerFormat="JWT",
 *     description="Enter your Sanctum token: Bearer {token}"
 * )
 *
 * @OA\Schema(
 *     schema="PaginationMeta",
 *     type="object",
 *     @OA\Property(property="total", type="integer", example=100),
 *     @OA\Property(property="per_page", type="integer", example=20),
 *     @OA\Property(property="current_page", type="integer", example=1),
 *     @OA\Property(property="last_page", type="integer", example=5),
 *     @OA\Property(property="from", type="integer", example=1),
 *     @OA\Property(property="to", type="integer", example=20),
 *     @OA\Property(property="has_more", type="boolean", example=true)
 * )
 *
 * @OA\Schema(
 *     schema="PaginationLinks",
 *     type="object",
 *     @OA\Property(property="first", type="string", nullable=true),
 *     @OA\Property(property="last", type="string", nullable=true),
 *     @OA\Property(property="prev", type="string", nullable=true),
 *     @OA\Property(property="next", type="string", nullable=true)
 * )
 *
 * @OA\Schema(
 *     schema="ErrorResponse",
 *     type="object",
 *     @OA\Property(property="success", type="boolean", example=false),
 *     @OA\Property(property="message", type="string", example="Error message"),
 *     @OA\Property(property="request_id", type="string", example="550e8400-e29b-41d4-a716-446655440000")
 * )
 *
 * @OA\Schema(
 *     schema="User",
 *     type="object",
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="name", type="string", example="Juan dela Cruz"),
 *     @OA\Property(property="email", type="string", format="email", example="juan@example.com"),
 *     @OA\Property(property="phone_number", type="string", nullable=true, example="+639171234567"),
 *     @OA\Property(property="role", type="string", enum={"admin","customer","driver","partner_admin"}, example="customer"),
 *     @OA\Property(property="created_at", type="string", format="date-time")
 * )
 *
 * @OA\Schema(
 *     schema="Order",
 *     type="object",
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="customer_id", type="integer", example=5),
 *     @OA\Property(property="service_id", type="integer", example=2),
 *     @OA\Property(property="status", type="string", enum={"pending","searching_driver","accepted","picked_up","completed","cancelled"}, example="pending"),
 *     @OA\Property(property="payment_status", type="string", enum={"pending","paid","failed"}, example="pending"),
 *     @OA\Property(property="estimated_fare", type="number", format="float", example=85.50),
 *     @OA\Property(property="estimated_distance_m", type="integer", example=5200),
 *     @OA\Property(property="payment_method", type="string", enum={"cash","gcash","maya","card","wallet"}, example="cash"),
 *     @OA\Property(property="created_at", type="string", format="date-time")
 * )
 *
 * @OA\Schema(
 *     schema="Payment",
 *     type="object",
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="order_id", type="integer", example=10),
 *     @OA\Property(property="customer_id", type="integer", example=5),
 *     @OA\Property(property="amount", type="number", format="float", example=85.50),
 *     @OA\Property(property="currency", type="string", example="PHP"),
 *     @OA\Property(property="method", type="string", enum={"cash","gcash","maya","card","wallet"}, example="gcash"),
 *     @OA\Property(property="status", type="string", enum={"pending","paid","failed","refunded"}, example="pending"),
 *     @OA\Property(property="created_at", type="string", format="date-time")
 * )
 *
 * @OA\Schema(
 *     schema="DriverProfile",
 *     type="object",
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="user_id", type="integer", example=3),
 *     @OA\Property(property="status", type="string", enum={"pending","active","offline","suspended"}, example="active"),
 *     @OA\Property(property="rating", type="number", format="float", example=4.8),
 *     @OA\Property(property="total_trips", type="integer", example=120),
 *     @OA\Property(property="license_number", type="string", example="N01-23-456789"),
 *     @OA\Property(property="last_latitude", type="number", format="float", nullable=true, example=14.5995),
 *     @OA\Property(property="last_longitude", type="number", format="float", nullable=true, example=120.9842)
 * )
 *
 * @OA\Schema(
 *     schema="Partner",
 *     type="object",
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="name", type="string", example="Jollibee Delivery"),
 *     @OA\Property(property="status", type="string", enum={"active","inactive","suspended"}, example="active"),
 *     @OA\Property(property="created_at", type="string", format="date-time")
 * )
 *
 * @OA\Schema(
 *     schema="Wallet",
 *     type="object",
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="user_id", type="integer", example=5),
 *     @OA\Property(property="balance", type="number", format="float", example=250.00),
 *     @OA\Property(property="currency", type="string", example="PHP")
 * )
 *
 * @OA\Schema(
 *     schema="Address",
 *     type="object",
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="user_id", type="integer", example=5),
 *     @OA\Property(property="label", type="string", example="Home"),
 *     @OA\Property(property="address_line1", type="string", example="123 Rizal St"),
 *     @OA\Property(property="latitude", type="number", format="float", example=14.5995),
 *     @OA\Property(property="longitude", type="number", format="float", example=120.9842),
 *     @OA\Property(property="is_default", type="boolean", example=true)
 * )
 */
