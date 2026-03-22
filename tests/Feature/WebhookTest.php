<?php

namespace Tests\Feature;

use App\Jobs\ProcessPaymentWebhookJob;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class WebhookTest extends TestCase
{
    use RefreshDatabase;

    private function createPendingPayment(): Payment
    {
        $customer = User::factory()->create(['role' => 'customer']);
        $service  = Service::factory()->create();
        $order    = Order::factory()->create(['customer_id' => $customer->id, 'service_id' => $service->id]);

        return Payment::factory()->create([
            'order_id'           => $order->id,
            'customer_id'        => $customer->id,
            'provider'           => 'xendit',
            'provider_reference' => 'inv_test_123',
        ]);
    }

    public function test_xendit_webhook_dispatches_job(): void
    {
        Queue::fake();

        // No webhook token configured → passes through
        $this->postJson('/api/v1/webhooks/payments/xendit', [
            'id'     => 'inv_test_123',
            'status' => 'PAID',
        ])->assertStatus(200);

        Queue::assertPushed(ProcessPaymentWebhookJob::class);
    }

    public function test_xendit_webhook_rejected_with_wrong_token(): void
    {
        config(['services.xendit.webhook_token' => 'correct-token']);

        $this->postJson('/api/v1/webhooks/payments/xendit', [
            'id'     => 'inv_test_123',
            'status' => 'PAID',
        ], ['X-CALLBACK-TOKEN' => 'wrong-token'])->assertStatus(400);
    }

    public function test_xendit_webhook_accepted_with_correct_token(): void
    {
        Queue::fake();
        config(['services.xendit.webhook_token' => 'correct-token']);

        $this->postJson('/api/v1/webhooks/payments/xendit', [
            'id'     => 'inv_test_123',
            'status' => 'PAID',
        ], ['X-CALLBACK-TOKEN' => 'correct-token'])->assertStatus(200);

        Queue::assertPushed(ProcessPaymentWebhookJob::class);
    }

    public function test_webhook_job_marks_payment_as_paid(): void
    {
        $payment = $this->createPendingPayment();

        $job = new ProcessPaymentWebhookJob('xendit', [
            'id'     => 'inv_test_123',
            'status' => 'PAID',
        ]);

        $job->handle();

        $this->assertDatabaseHas('payments', [
            'id'     => $payment->id,
            'status' => 'paid',
        ]);
    }

    public function test_webhook_job_marks_payment_as_failed(): void
    {
        $payment = $this->createPendingPayment();

        $job = new ProcessPaymentWebhookJob('xendit', [
            'id'     => 'inv_test_123',
            'status' => 'EXPIRED',
        ]);

        $job->handle();

        $this->assertDatabaseHas('payments', [
            'id'     => $payment->id,
            'status' => 'failed',
        ]);
    }

    public function test_webhook_job_is_idempotent(): void
    {
        $payment = $this->createPendingPayment();
        $payment->update(['status' => 'paid', 'paid_at' => now()]);

        $job = new ProcessPaymentWebhookJob('xendit', [
            'id'     => 'inv_test_123',
            'status' => 'PAID',
        ]);

        $job->handle(); // second call — should not throw or double-process

        $this->assertDatabaseHas('payments', ['id' => $payment->id, 'status' => 'paid']);
    }

    public function test_invalid_provider_route_returns_404(): void
    {
        $this->postJson('/api/v1/webhooks/payments/stripe', ['id' => 'x'])
             ->assertStatus(404);
    }
}
