<?php

namespace Tests\Feature;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationTest extends TestCase
{
    use RefreshDatabase;

    private function createNotification(User $user, array $overrides = []): Notification
    {
        return Notification::create(array_merge([
            'user_id' => $user->id,
            'type'    => 'order.created',
            'title'   => 'Order Created',
            'body'    => 'Your order has been placed.',
            'channel' => 'in_app',
            'data'    => [],
        ], $overrides));
    }

    public function test_user_can_list_own_notifications(): void
    {
        $user = $this->actingAsRole('customer');
        $this->createNotification($user);
        $this->createNotification($user);

        $response = $this->getJson('/api/v1/notifications');
        $response->assertStatus(200);
        $this->assertCount(2, $response->json('data'));
    }

    public function test_user_cannot_see_other_users_notifications(): void
    {
        $user  = $this->actingAsRole('customer');
        $other = User::factory()->create(['role' => 'customer']);

        $this->createNotification($other);
        $this->createNotification($user);

        $response = $this->getJson('/api/v1/notifications');
        $this->assertCount(1, $response->json('data'));
    }

    public function test_unread_count_returns_correct_number(): void
    {
        $user = $this->actingAsRole('customer');

        $this->createNotification($user);
        $this->createNotification($user);
        $this->createNotification($user, ['read_at' => now()]);

        $response = $this->getJson('/api/v1/notifications/unread-count');
        $response->assertStatus(200)
                 ->assertJsonPath('data.unread_count', 2);
    }

    public function test_user_can_mark_notification_as_read(): void
    {
        $user         = $this->actingAsRole('customer');
        $notification = $this->createNotification($user);

        $this->patchJson("/api/v1/notifications/{$notification->id}/read")
             ->assertStatus(200);

        $this->assertNotNull(Notification::find($notification->id)->read_at);
    }

    public function test_user_can_mark_all_notifications_as_read(): void
    {
        $user = $this->actingAsRole('customer');
        $this->createNotification($user);
        $this->createNotification($user);

        $this->postJson('/api/v1/notifications/read-all')->assertStatus(200);

        $unread = Notification::where('user_id', $user->id)->whereNull('read_at')->count();
        $this->assertEquals(0, $unread);
    }

    public function test_notifications_require_authentication(): void
    {
        $this->getJson('/api/v1/notifications')->assertStatus(401);
    }
}
