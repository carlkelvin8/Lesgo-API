<?php

namespace Tests\Feature;

use App\Models\Service;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_can_list_services(): void
    {
        Service::factory()->count(3)->create(['is_active' => true]);

        $this->getJson('/api/v1/services')
             ->assertStatus(200)
             ->assertJsonPath('success', true);
    }

    public function test_public_can_view_single_service(): void
    {
        $service = Service::factory()->create(['is_active' => true]);

        $this->getJson("/api/v1/services/{$service->id}")
             ->assertStatus(200)
             ->assertJsonPath('data.id', $service->id);
    }

    public function test_service_returns_404_for_nonexistent(): void
    {
        $this->getJson('/api/v1/services/99999')->assertStatus(404);
    }
}
