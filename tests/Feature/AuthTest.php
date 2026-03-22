<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    // ── Register ──────────────────────────────────────────────────────────────

    public function test_customer_can_register(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'name'                  => 'Juan dela Cruz',
            'email'                 => 'juan@example.com',
            'phone_number'          => '+639171234567',
            'password'              => 'password123',
            'password_confirmation' => 'password123',
            'role'                  => 'customer',
        ]);

        $response->assertStatus(201)
                 ->assertJsonPath('success', true)
                 ->assertJsonStructure(['token', 'user' => ['id', 'email', 'role']]);

        $this->assertDatabaseHas('users', ['email' => 'juan@example.com', 'role' => 'customer']);
    }

    public function test_register_fails_with_duplicate_email(): void
    {
        User::factory()->create(['email' => 'taken@example.com']);

        $this->postJson('/api/v1/auth/register', [
            'name'                  => 'Another User',
            'email'                 => 'taken@example.com',
            'password'              => 'password123',
            'password_confirmation' => 'password123',
            'role'                  => 'customer',
        ])->assertStatus(422);
    }

    public function test_register_fails_with_invalid_role(): void
    {
        $this->postJson('/api/v1/auth/register', [
            'name'                  => 'Hacker',
            'email'                 => 'hack@example.com',
            'password'              => 'password123',
            'password_confirmation' => 'password123',
            'role'                  => 'admin', // not allowed via registration
        ])->assertStatus(422);
    }

    public function test_register_requires_password_confirmation(): void
    {
        $this->postJson('/api/v1/auth/register', [
            'name'     => 'Test',
            'email'    => 'test@example.com',
            'password' => 'password123',
            'role'     => 'customer',
        ])->assertStatus(422);
    }

    // ── Login ─────────────────────────────────────────────────────────────────

    public function test_user_can_login(): void
    {
        $user = User::factory()->create(['password' => bcrypt('secret123')]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email'    => $user->email,
            'password' => 'secret123',
        ]);

        $response->assertStatus(200)
                 ->assertJsonPath('success', true)
                 ->assertJsonStructure(['token', 'user']);
    }

    public function test_login_fails_with_wrong_password(): void
    {
        $user = User::factory()->create(['password' => bcrypt('correct')]);

        $this->postJson('/api/v1/auth/login', [
            'email'    => $user->email,
            'password' => 'wrong',
        ])->assertStatus(401);
    }

    public function test_login_fails_with_unknown_email(): void
    {
        $this->postJson('/api/v1/auth/login', [
            'email'    => 'nobody@example.com',
            'password' => 'password',
        ])->assertStatus(401);
    }

    // ── Me ────────────────────────────────────────────────────────────────────

    public function test_me_returns_authenticated_user(): void
    {
        $user = $this->actingAsRole('customer');

        $this->getJson('/api/v1/auth/me')
             ->assertStatus(200)
             ->assertJsonPath('user.id', $user->id)
             ->assertJsonPath('user.role', 'customer');
    }

    public function test_me_requires_authentication(): void
    {
        $this->getJson('/api/v1/auth/me')->assertStatus(401);
    }

    // ── Logout ────────────────────────────────────────────────────────────────

    public function test_user_can_logout(): void
    {
        $this->actingAsRole('customer');

        $this->postJson('/api/v1/auth/logout')
             ->assertStatus(200)
             ->assertJsonPath('success', true);
    }

    public function test_user_can_logout_all_devices(): void
    {
        $this->actingAsRole('customer');

        $this->postJson('/api/v1/auth/logout-all')
             ->assertStatus(200)
             ->assertJsonPath('success', true);
    }

    // ── FCM Token ─────────────────────────────────────────────────────────────

    public function test_user_can_register_fcm_token(): void
    {
        $user = $this->actingAsRole('customer');

        $this->postJson('/api/v1/auth/fcm-token', ['fcm_token' => 'test-fcm-token-abc123'])
             ->assertStatus(200)
             ->assertJsonPath('success', true);

        $this->assertDatabaseHas('users', ['id' => $user->id, 'fcm_token' => 'test-fcm-token-abc123']);
    }

    public function test_fcm_token_requires_authentication(): void
    {
        $this->postJson('/api/v1/auth/fcm-token', ['fcm_token' => 'token'])
             ->assertStatus(401);
    }

    // ── Update Profile ────────────────────────────────────────────────────────

    public function test_user_can_update_name(): void
    {
        $user = $this->actingAsRole('customer');

        $this->putJson('/api/v1/auth/me', ['name' => 'New Name'])
             ->assertStatus(200)
             ->assertJsonPath('user.name', 'New Name');
    }
}
