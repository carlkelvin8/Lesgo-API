<?php

namespace Tests;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    // ── Auth helpers ──────────────────────────────────────────────────────────

    protected function actingAsRole(string $role): User
    {
        $user = User::factory()->create(['role' => $role]);
        $this->actingAs($user, 'sanctum');
        return $user;
    }

    protected function tokenFor(User $user): string
    {
        return $user->createToken('test')->plainTextToken;
    }

    // ── Response assertion helpers ────────────────────────────────────────────

    protected function assertApiSuccess($response, int $status = 200): void
    {
        $response->assertStatus($status)
                 ->assertJsonPath('success', true);
    }

    protected function assertApiError($response, int $status): void
    {
        $response->assertStatus($status)
                 ->assertJsonPath('success', false);
    }

    protected function assertPaginated($response): void
    {
        $response->assertJsonStructure([
            'success', 'data', 'meta' => ['total', 'per_page', 'current_page', 'last_page'],
        ]);
    }
}
