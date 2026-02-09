<?php

namespace Tests\Feature;

use App\Models\ChecklistTemplate;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class LesbuyTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_list_checklist_templates()
    {
        // Arrange
        ChecklistTemplate::create(['name' => 'Milk', 'category' => 'Dairy', 'default_price' => 100]);
        ChecklistTemplate::create(['name' => 'Bread', 'category' => 'Bakery', 'default_price' => 50]);

        $user = User::factory()->create();

        // Act
        $response = $this->actingAs($user)->getJson('/api/v1/checklist-templates');

        // Assert
        $response->assertStatus(200)
            ->assertJsonCount(2)
            ->assertJsonFragment(['name' => 'Milk']);
    }

    public function test_can_create_checklist_template()
    {
        // Arrange
        $user = User::factory()->create(); // Ideally admin

        // Act
        $response = $this->actingAs($user)->postJson('/api/v1/checklist-templates', [
            'name' => 'Eggs',
            'category' => 'Dairy',
            'default_price' => 120,
        ]);

        // Assert
        $response->assertStatus(201)
            ->assertJsonFragment(['name' => 'Eggs']);

        $this->assertDatabaseHas('checklist_templates', ['name' => 'Eggs']);
    }

    public function test_can_create_order_with_lesbuy_items()
    {
        // Arrange
        $user = User::factory()->create(['role' => 'customer']);
        $service = Service::create([
            'code' => 'LESBUY',
            'name' => 'Lesbuy',
            'base_fare' => 50,
            'per_km_rate' => 10,
            'per_minute_rate' => 2,
            'minimum_fare' => 60,
            'is_active' => true,
        ]);

        $payload = [
            'service_id' => $service->id,
            'estimated_distance_m' => 5000,
            'pickup' => [
                'address' => 'Store',
                'lat' => 14.0,
                'lng' => 121.0,
            ],
            'dropoff' => [
                'address' => 'Home',
                'lat' => 14.1,
                'lng' => 121.1,
            ],
            'items' => [
                [
                    'name' => 'Milk',
                    'quantity' => 2,
                    'estimated_price' => 100,
                    'is_checklist_item' => true,
                ],
                [
                    'name' => 'Special Request',
                    'quantity' => 1,
                    'estimated_price' => 0,
                    'is_checklist_item' => false,
                ]
            ]
        ];

        // Act
        $response = $this->actingAs($user)->postJson('/api/v1/orders', $payload);

        // Assert
        $response->assertStatus(201);
        $orderId = $response->json('id');

        $this->assertDatabaseHas('orders', ['id' => $orderId]);
        $this->assertDatabaseHas('lesbuy_items', ['order_id' => $orderId, 'name' => 'Milk', 'quantity' => 2]);
        $this->assertDatabaseHas('lesbuy_items', ['order_id' => $orderId, 'name' => 'Special Request']);
    }

    public function test_can_get_receipt()
    {
        // Arrange
        $user = User::factory()->create(['role' => 'customer']);
        $service = Service::create(['code' => 'LESBUY', 'name' => 'Lesbuy']);
        
        // Create order via factory or manual
        // Note: We need a factory for Order ideally, but I'll do it manually to be safe
        $payload = [
            'service_id' => $service->id,
            'estimated_distance_m' => 5000,
            'pickup' => ['address' => 'A', 'lat' => 0, 'lng' => 0],
            'dropoff' => ['address' => 'B', 'lat' => 0, 'lng' => 0],
            'items' => [
                ['name' => 'Item 1', 'quantity' => 1, 'estimated_price' => 50]
            ]
        ];
        
        $orderResponse = $this->actingAs($user)->postJson('/api/v1/orders', $payload);
        $orderId = $orderResponse->json('id');

        // Act
        $response = $this->actingAs($user)->getJson("/api/v1/orders/{$orderId}/receipt");

        // Assert
        $response->assertStatus(200)
            ->assertJsonStructure([
                'order_id',
                'transaction_id',
                'items' => [
                    '*' => ['name', 'quantity', 'price', 'total']
                ],
                'breakdown' => ['base_fare', 'distance_fare', 'items_total', 'total_amount']
            ]);
    }
}
