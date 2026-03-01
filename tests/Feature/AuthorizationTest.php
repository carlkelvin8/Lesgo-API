<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_only_view_own_profile(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        
        $token = $user1->createToken('test')->plainTextToken;

        // Can view own profile
        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/users/{$user1->id}");
        $response->assertStatus(200);

        // Cannot view other user's profile
        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/users/{$user2->id}");
        $response->assertStatus(403);
    }

    public function test_admin_can_view_all_profiles(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $user = User::factory()->create(['role' => 'customer']);
        
        $token = $admin->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/users/{$user->id}");

        $response->assertStatus(200);
    }

    public function test_user_can_only_update_own_profile(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        
        $token = $user1->createToken('test')->plainTextToken;

        // Cannot update other user's profile
        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->patchJson("/api/v1/users/{$user2->id}", [
                'name' => 'Hacked Name',
            ]);

        $response->assertStatus(403);
    }

    public function test_only_admin_can_delete_users(): void
    {
        $user = User::factory()->create(['role' => 'customer']);
        $targetUser = User::factory()->create();
        
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->deleteJson("/api/v1/users/{$targetUser->id}");

        $response->assertStatus(403);
    }

    public function test_user_can_view_own_orders(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->create(['customer_id' => $user->id]);
        
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/orders/{$order->id}");

        $response->assertStatus(200);
    }

    public function test_user_cannot_view_others_orders(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $order = Order::factory()->create(['customer_id' => $user2->id]);
        
        $token = $user1->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/orders/{$order->id}");

        $response->assertStatus(403);
    }

    public function test_driver_can_view_assigned_orders(): void
    {
        $driver = User::factory()->create(['role' => 'driver']);
        $order = Order::factory()->create(['driver_id' => $driver->id]);
        
        $token = $driver->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/orders/{$order->id}");

        $response->assertStatus(200);
    }

    public function test_only_customer_can_create_orders(): void
    {
        $driver = User::factory()->create(['role' => 'driver']);
        $token = $driver->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/orders', [
                'service_id' => 1,
                'pickup_address' => 'Test Address',
            ]);

        $response->assertStatus(403);
    }
}
