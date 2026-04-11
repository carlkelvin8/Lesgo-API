<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\User;
use App\Models\Wallet;
use App\Models\DriverProfile;
use App\Services\WalletValidationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WalletValidationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Set up the wallet threshold setting
        WalletValidationService::setMinimumThreshold(100.00);
    }

    public function test_driver_with_sufficient_balance_can_accept_booking(): void
    {
        // Create driver with sufficient balance
        $driver = User::factory()->create(['role' => 'driver']);
        $driverProfile = DriverProfile::factory()->create(['user_id' => $driver->id]);
        Wallet::create([
            'user_id' => $driver->id,
            'balance' => 150.00,
            'currency' => 'PHP'
        ]);

        // Create an order
        $customer = User::factory()->create(['role' => 'customer']);
        $order = Order::factory()->create([
            'customer_id' => $customer->id,
            'status' => 'pending'
        ]);

        // Driver should be able to accept the booking
        $response = $this->actingAs($driver)
            ->patchJson("/api/v1/orders/{$order->id}/status", [
                'status' => 'accepted'
            ]);

        $response->assertStatus(200);
        $this->assertEquals('accepted', $order->fresh()->status);
    }

    public function test_driver_with_insufficient_balance_cannot_accept_booking(): void
    {
        // Create driver with insufficient balance
        $driver = User::factory()->create(['role' => 'driver']);
        $driverProfile = DriverProfile::factory()->create(['user_id' => $driver->id]);
        Wallet::create([
            'user_id' => $driver->id,
            'balance' => 50.00, // Below threshold of 100
            'currency' => 'PHP'
        ]);

        // Create an order
        $customer = User::factory()->create(['role' => 'customer']);
        $order = Order::factory()->create([
            'customer_id' => $customer->id,
            'status' => 'pending'
        ]);

        // Driver should NOT be able to accept the booking
        $response = $this->actingAs($driver)
            ->patchJson("/api/v1/orders/{$order->id}/status", [
                'status' => 'accepted'
            ]);

        $response->assertStatus(422);
        $response->assertJsonStructure([
            'success',
            'message',
            'wallet_validation' => [
                'has_sufficient_balance',
                'current_balance',
                'minimum_threshold',
                'shortfall'
            ]
        ]);
        
        $this->assertEquals('pending', $order->fresh()->status);
    }

    public function test_driver_without_wallet_cannot_accept_booking(): void
    {
        // Create driver without wallet
        $driver = User::factory()->create(['role' => 'driver']);
        $driverProfile = DriverProfile::factory()->create(['user_id' => $driver->id]);

        // Create an order
        $customer = User::factory()->create(['role' => 'customer']);
        $order = Order::factory()->create([
            'customer_id' => $customer->id,
            'status' => 'pending'
        ]);

        // Driver should NOT be able to accept the booking
        $response = $this->actingAs($driver)
            ->patchJson("/api/v1/orders/{$order->id}/status", [
                'status' => 'accepted'
            ]);

        $response->assertStatus(422);
        $this->assertEquals('pending', $order->fresh()->status);
    }

    public function test_wallet_validation_endpoint_returns_correct_data(): void
    {
        $driver = User::factory()->create(['role' => 'driver']);
        Wallet::create([
            'user_id' => $driver->id,
            'balance' => 75.00,
            'currency' => 'PHP'
        ]);

        $response = $this->actingAs($driver)
            ->getJson('/api/v1/wallets/my/validation');

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'data' => [
                'has_sufficient_balance' => false,
                'current_balance' => 75.00,
                'minimum_threshold' => 100.00,
                'shortfall' => 25.00
            ]
        ]);
    }

    public function test_customer_is_not_affected_by_wallet_validation(): void
    {
        // Customers should not be affected by wallet validation
        $customer = User::factory()->create(['role' => 'customer']);
        
        $validation = WalletValidationService::validateBalance($customer);
        
        $this->assertTrue($validation['has_sufficient_balance']);
        $this->assertEquals(0, $validation['current_balance']);
        $this->assertEquals(0, $validation['shortfall']);
    }

    public function test_admin_can_update_wallet_threshold(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)
            ->putJson('/api/v1/admin/wallet-settings/threshold', [
                'threshold' => 200.00
            ]);

        $response->assertStatus(200);
        $this->assertEquals(200.00, WalletValidationService::getMinimumThreshold());
    }

    public function test_non_admin_cannot_update_wallet_threshold(): void
    {
        $driver = User::factory()->create(['role' => 'driver']);

        $response = $this->actingAs($driver)
            ->putJson('/api/v1/admin/wallet-settings/threshold', [
                'threshold' => 200.00
            ]);

        $response->assertStatus(403);
    }
}