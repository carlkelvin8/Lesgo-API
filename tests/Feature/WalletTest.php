<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WalletTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_view_own_wallet(): void
    {
        $user   = $this->actingAsRole('customer');
        $wallet = Wallet::factory()->create(['user_id' => $user->id, 'balance' => 500.00]);

        $this->getJson("/api/v1/wallets/{$user->id}")
             ->assertStatus(200)
             ->assertJsonPath('data.balance', '500.00');
    }

    public function test_user_cannot_view_another_users_wallet(): void
    {
        $this->actingAsRole('customer');
        $other  = User::factory()->create(['role' => 'customer']);
        Wallet::factory()->create(['user_id' => $other->id]);

        $this->getJson("/api/v1/wallets/{$other->id}")->assertStatus(403);
    }

    public function test_admin_can_view_any_wallet(): void
    {
        $this->actingAsRole('admin');
        $other  = User::factory()->create(['role' => 'customer']);
        $wallet = Wallet::factory()->create(['user_id' => $other->id, 'balance' => 250.00]);

        $this->getJson("/api/v1/wallets/{$other->id}")
             ->assertStatus(200)
             ->assertJsonPath('data.balance', '250.00');
    }

    public function test_wallet_returns_404_when_not_found(): void
    {
        $user = $this->actingAsRole('customer');

        // No wallet created — should 404
        $this->getJson("/api/v1/wallets/{$user->id}")->assertStatus(404);
    }

    public function test_wallet_requires_authentication(): void
    {
        $this->getJson('/api/v1/wallets/1')->assertStatus(401);
    }

    public function test_user_can_view_own_transactions(): void
    {
        $user   = $this->actingAsRole('customer');
        $wallet = Wallet::factory()->create(['user_id' => $user->id]);

        $this->getJson("/api/v1/wallets/{$user->id}/transactions")
             ->assertStatus(200)
             ->assertJsonStructure(['success', 'data']);
    }

    public function test_transactions_filter_rejects_invalid_type(): void
    {
        $user = $this->actingAsRole('customer');
        Wallet::factory()->create(['user_id' => $user->id]);

        $this->getJson("/api/v1/wallets/{$user->id}/transactions?type=invalid")
             ->assertStatus(422);
    }
}
