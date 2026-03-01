<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_sql_injection_is_prevented(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/users?email=' OR '1'='1");

        // Should not cause SQL error
        $response->assertStatus(200);
    }

    public function test_xss_input_is_sanitized(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => '<script>alert("XSS")</script>John',
            'email' => 'test@example.com',
            'password' => 'SecureP@ss123',
            'password_confirmation' => 'SecureP@ss123',
            'role' => 'customer',
        ]);

        $response->assertStatus(201);
        
        $user = User::where('email', 'test@example.com')->first();
        // Script tags should be removed or escaped
        $this->assertStringNotContainsString('<script>', $user->name);
    }

    public function test_mass_assignment_is_prevented(): void
    {
        $user = User::factory()->create(['role' => 'customer']);
        $token = $user->createToken('test')->plainTextToken;

        // Try to escalate role via mass assignment
        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson('/api/v1/auth/me', [
                'name' => 'Updated Name',
                'role' => 'admin', // Should not be allowed
            ]);

        $user->refresh();
        
        // Role should not have changed
        $this->assertEquals('customer', $user->role);
    }

    public function test_security_headers_are_present(): void
    {
        $response = $this->getJson('/api/v1/ping');

        $response->assertHeader('X-Frame-Options', 'DENY');
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('X-XSS-Protection', '1; mode=block');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
    }

    public function test_sensitive_data_not_exposed_in_errors(): void
    {
        $response = $this->getJson('/api/v1/nonexistent-endpoint');

        $response->assertStatus(404);
        
        $content = $response->getContent();
        
        // Should not expose file paths, SQL queries, etc.
        $this->assertStringNotContainsString('vendor/', $content);
        $this->assertStringNotContainsString('SELECT', $content);
        $this->assertStringNotContainsString('app/', $content);
    }

    public function test_password_not_returned_in_responses(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/auth/me');

        $response->assertStatus(200);
        $response->assertJsonMissing(['password']);
        
        $content = $response->getContent();
        $this->assertStringNotContainsString('$2y$', $content); // bcrypt hash prefix
    }

    public function test_tokens_not_logged_or_exposed(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/auth/me');

        $content = $response->getContent();
        
        // Token should not be in response
        $this->assertStringNotContainsString($token, $content);
    }

    public function test_path_traversal_is_prevented(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/users/../../../etc/passwd');

        // Should return 404, not expose file system
        $response->assertStatus(404);
    }

    public function test_invalid_json_handled_gracefully(): void
    {
        $response = $this->postJson('/api/v1/auth/login', 'invalid-json');

        $response->assertStatus(400);
    }

    public function test_oversized_request_rejected(): void
    {
        $largePayload = str_repeat('a', 10 * 1024 * 1024); // 10MB

        $response = $this->postJson('/api/v1/auth/register', [
            'name' => $largePayload,
            'email' => 'test@example.com',
            'password' => 'SecureP@ss123',
            'password_confirmation' => 'SecureP@ss123',
            'role' => 'customer',
        ]);

        // Should be rejected (either 413 or 422)
        $this->assertContains($response->status(), [413, 422]);
    }

    public function test_csrf_token_not_required_for_api(): void
    {
        // API should use token auth, not CSRF
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'test@example.com',
            'password' => 'password',
        ]);

        // Should not get CSRF error
        $this->assertNotEquals(419, $response->status());
    }
}
