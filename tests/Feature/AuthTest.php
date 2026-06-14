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
                 ->assertJsonStructure([
                     'token',
                     'refresh_token',
                     'user' => ['id', 'email', 'role'],
                 ]);

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
                 ->assertJsonStructure(['token', 'refresh_token', 'user']);
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

    // ── Refresh token ─────────────────────────────────────────────────────────

    public function test_refresh_with_refresh_token_issues_new_pair(): void
    {
        $user = User::factory()->create(['password' => bcrypt('secret123')]);

        $login = $this->postJson('/api/v1/auth/login', [
            'email'    => $user->email,
            'password' => 'secret123',
        ])->assertStatus(200);

        $refreshToken = $login->json('refresh_token');
        $this->assertNotEmpty($refreshToken);

        $refresh = $this->postJson('/api/v1/auth/refresh', [
            'refresh_token' => $refreshToken,
        ])->assertStatus(200)
          ->assertJsonPath('success', true)
          ->assertJsonStructure(['token', 'refresh_token']);

        $this->assertNotEquals($refreshToken, $refresh->json('refresh_token'));
    }

    public function test_refresh_rejects_invalid_refresh_token(): void
    {
        $this->postJson('/api/v1/auth/refresh', [
            'refresh_token' => 'invalid-token',
        ])->assertStatus(401)
          ->assertJsonPath('success', false);
    }

    // ── Change password ───────────────────────────────────────────────────────

    public function test_user_can_change_password(): void
    {
        $user = User::factory()->create(['password' => bcrypt('OldPass123')]);
        $token = $this->tokenFor($user);

        $this->withToken($token)->postJson('/api/v1/auth/change-password', [
            'current_password' => 'OldPass123',
            'password' => 'NewPass123!',
            'password_confirmation' => 'NewPass123!',
        ])->assertStatus(200)
          ->assertJsonPath('success', true)
          ->assertJsonStructure(['token', 'refresh_token']);

        $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'NewPass123!',
        ])->assertStatus(200);
    }

    public function test_change_password_rejects_wrong_current_password(): void
    {
        $user = User::factory()->create(['password' => bcrypt('OldPass123')]);
        $token = $this->tokenFor($user);

        $this->withToken($token)->postJson('/api/v1/auth/change-password', [
            'current_password' => 'WrongPass123',
            'password' => 'NewPass123!',
            'password_confirmation' => 'NewPass123!',
        ])->assertStatus(422)
          ->assertJsonPath('success', false);
    }

    // ── Deactivate account ────────────────────────────────────────────────────

    public function test_user_can_deactivate_account(): void
    {
        $user = User::factory()->create([
            'password' => bcrypt('Secret123'),
            'is_active' => true,
        ]);
        $token = $this->tokenFor($user);

        $this->withToken($token)->postJson('/api/v1/auth/account/deactivate', [
            'password' => 'Secret123',
            'reason' => 'Testing deactivation',
        ])->assertStatus(200)
          ->assertJsonPath('success', true);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'is_active' => false,
        ]);

        $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'Secret123',
        ])->assertStatus(401);
    }

    public function test_customer_can_permanently_delete_account(): void
    {
        $user = User::factory()->create([
            'password' => bcrypt('Secret123'),
            'role' => 'customer',
            'is_active' => true,
        ]);
        $token = $this->tokenFor($user);
        $originalEmail = $user->email;

        $this->withToken($token)->postJson('/api/v1/auth/account/delete', [
            'password' => 'Secret123',
            'confirmation' => 'DELETE',
            'reason' => 'No longer needed',
        ])->assertStatus(200)
          ->assertJsonPath('success', true);

        $user->refresh();

        $this->assertTrue(str_ends_with($user->email, '@deleted.local'));
        $this->assertSame('Deleted User', $user->name);
        $this->assertFalse((bool) $user->is_active);

        $this->postJson('/api/v1/auth/login', [
            'email' => $originalEmail,
            'password' => 'Secret123',
        ])->assertStatus(401);
    }

    public function test_permanent_delete_requires_delete_confirmation(): void
    {
        $user = User::factory()->create([
            'password' => bcrypt('Secret123'),
            'role' => 'customer',
        ]);
        $token = $this->tokenFor($user);

        $this->withToken($token)->postJson('/api/v1/auth/account/delete', [
            'password' => 'Secret123',
            'confirmation' => 'WRONG',
        ])->assertStatus(422);
    }

    public function test_driver_cannot_permanently_delete_via_customer_endpoint(): void
    {
        $user = User::factory()->driver()->create([
            'password' => bcrypt('Secret123'),
        ]);
        $token = $this->tokenFor($user);

        $this->withToken($token)->postJson('/api/v1/auth/account/delete', [
            'password' => 'Secret123',
            'confirmation' => 'DELETE',
        ])->assertStatus(422)
          ->assertJsonPath('success', false);
    }
}
