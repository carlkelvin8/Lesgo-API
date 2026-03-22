<?php

namespace App\Jobs;

use App\Models\Order;
use App\Models\Payment;
use App\Services\NotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProcessPaymentWebhookJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;
    public int $backoff = 60;

    public function __construct(
        public string $provider,
        public array  $payload
    ) {}

    public function handle(): void
    {
        $reference = $this->payload['reference'] ?? $this->payload['id'] ?? null;
        $status    = $this->resolveStatus($this->payload['status'] ?? '');

        if (!$reference || !$status) {
            Log::warning('ProcessPaymentWebhookJob: missing reference or status', $this->payload);
            return;
        }

        DB::transaction(function () use ($reference, $status) {
            $payment = Payment::where('provider_reference', $reference)->first();

            if (!$payment) {
                Log::warning('ProcessPaymentWebhookJob: payment not found', ['reference' => $reference]);
                return;
            }

            if ($payment->status === $status) {
                return; // idempotent — already processed
            }

            $payment->update([
                'status'  => $status,
                'paid_at' => $status === 'paid' ? now() : $payment->paid_at,
            ]);

            if ($status === 'paid') {
                Order::where('id', $payment->order_id)
                    ->update(['payment_status' => 'paid']);

                // Notify customer of successful payment
                if ($payment->customer_id) {
                    NotificationService::send(
                        user: $payment->customer_id,
                        type: 'payment.received',
                        title: 'Payment Confirmed',
                        body: "Payment of ₱{$payment->amount} for order #{$payment->order_id} was successful.",
                        data: [
                            'payment_id' => $payment->id,
                            'order_id'   => $payment->order_id,
                            'amount'     => $payment->amount,
                        ],
                        channel: 'push'
                    );
                }
            }

            Log::info('ProcessPaymentWebhookJob: payment updated', [
                'payment_id' => $payment->id,
                'status'     => $status,
                'provider'   => $this->provider,
            ]);
        });
    }

    private function resolveStatus(string $providerStatus): ?string
    {
        return match (strtolower($providerStatus)) {
            'paid', 'succeeded', 'completed', 'success' => 'paid',
            'failed', 'failure', 'declined'              => 'failed',
            'refunded', 'reversed'                       => 'refunded',
            default                                      => null,
        };
    }

    public function failed(\Throwable $e): void
    {
        Log::error('ProcessPaymentWebhookJob failed', [
            'provider' => $this->provider,
            'error'    => $e->getMessage(),
            'payload'  => $this->payload,
        ]);
    }
}
