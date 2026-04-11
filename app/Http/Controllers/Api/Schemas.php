<?php

namespace App\Http\Controllers\Api;

/**
 * @OA\Schema(
 *     schema="Order",
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="customer_id", type="integer", example=1),
 *     @OA\Property(property="driver_id", type="integer", nullable=true, example=1),
 *     @OA\Property(property="service_id", type="integer", example=1),
 *     @OA\Property(property="status", type="string", example="pending"),
 *     @OA\Property(property="pickup_address", type="string", example="123 Main St"),
 *     @OA\Property(property="pickup_lat", type="number", format="float", example=14.5995),
 *     @OA\Property(property="pickup_lng", type="number", format="float", example=120.9842),
 *     @OA\Property(property="dropoff_address", type="string", example="456 Oak Ave"),
 *     @OA\Property(property="dropoff_lat", type="number", format="float", example=14.6090),
 *     @OA\Property(property="dropoff_lng", type="number", format="float", example=121.0000),
 *     @OA\Property(property="estimated_fare", type="number", format="float", example=150.50),
 *     @OA\Property(property="actual_fare", type="number", format="float", nullable=true, example=155.00),
 *     @OA\Property(property="payment_method", type="string", example="cash"),
 *     @OA\Property(property="payment_status", type="string", example="pending"),
 *     @OA\Property(property="voucher_code", type="string", nullable=true, example="WELCOME10"),
 *     @OA\Property(property="discount_amount", type="number", format="float", example=15.05),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time"),
 *     @OA\Property(property="accepted_at", type="string", format="date-time", nullable=true),
 *     @OA\Property(property="picked_up_at", type="string", format="date-time", nullable=true),
 *     @OA\Property(property="completed_at", type="string", format="date-time", nullable=true),
 *     @OA\Property(property="cancelled_at", type="string", format="date-time", nullable=true)
 * )
 * 
 * @OA\Schema(
 *     schema="User",
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="name", type="string", example="John Doe"),
 *     @OA\Property(property="email", type="string", format="email", example="john@example.com"),
 *     @OA\Property(property="phone_number", type="string", example="+639171234567"),
 *     @OA\Property(property="role", type="string", example="customer"),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time")
 * )
 * 
 * @OA\Schema(
 *     schema="Service",
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="name", type="string", example="LeSGo Delivery"),
 *     @OA\Property(property="code", type="string", example="LESGO"),
 *     @OA\Property(property="description", type="string", example="Fast delivery service"),
 *     @OA\Property(property="base_fare", type="number", format="float", example=40.00),
 *     @OA\Property(property="per_km_rate", type="number", format="float", example=9.50),
 *     @OA\Property(property="minimum_fare", type="number", format="float", example=40.00),
 *     @OA\Property(property="icon_url", type="string", nullable=true, example="https://example.com/icon.png"),
 *     @OA\Property(property="is_active", type="boolean", example=true)
 * )
 * 
 * @OA\Schema(
 *     schema="DriverProfile",
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="user_id", type="integer", example=1),
 *     @OA\Property(property="license_number", type="string", example="DL123456789"),
 *     @OA\Property(property="license_expiry_date", type="string", format="date", example="2025-12-31"),
 *     @OA\Property(property="status", type="string", example="active"),
 *     @OA\Property(property="rating", type="number", format="float", example=4.8),
 *     @OA\Property(property="total_orders", type="integer", example=150),
 *     @OA\Property(property="completion_rate", type="number", format="float", example=98.5)
 * )
 * 
 * @OA\Schema(
 *     schema="Wallet",
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="user_id", type="integer", example=1),
 *     @OA\Property(property="balance", type="number", format="float", example=250.75),
 *     @OA\Property(property="currency", type="string", example="PHP"),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time")
 * )
 */
class Schemas
{
    // This class exists only to hold OpenAPI schema definitions
}