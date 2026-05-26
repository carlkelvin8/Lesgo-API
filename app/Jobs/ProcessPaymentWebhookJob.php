<?php

namespace App\Jobs;

use App\Models\Order;
use App\Models\Payment;
use App\Models\WalletTopUp;
use App\Services\NotificationService;
use App\Services\WalletService;
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
        // Xendit invoice webhook payload uses 'id' and 'status' at top level
        // e.g. { "id": "inv_xxx", "status": "PAID", "external_id": "lesgo-order-42-abc", ... }
        $reference = $this->payload['id']
            ?? $this->payload['reference']
            ?? null;

        $status = $this->resolveStatus(
            $this->payload['status'] ?? ''
        );

        if (!$reference || !$status) {
            Log::warning('ProcessPaymentWebhookJob: missing reference or status', $this->payload);
            return;
        }

        DB::transaction(function () use ($reference, $status) {
            $topUp = WalletTopUp::where('xendit_invoice_id', $reference)->first();

            if ($topUp) {
                if ($status === 'paid' && !$topUp->isPaid()) {
                    WalletService::completeTopUp($topUp);

                    NotificationService::send(
                        user: $topUp->user_id,
                        type: 'wallet.topup',
                        title: 'LesPay Top-up Successful',
                        body: '₱' . number_format((float) $topUp->amount, 2) . ' has been added to your wallet.',
                        data: [
                            'wallet_top_up_id' => $topUp->id,
                            'amount'           => $topUp->amount,
                        ],
                        channel: 'push'
                    );
                } elseif ($status === 'failed' && !$topUp->isPaid()) {
                    $topUp->update(['status' => 'failed']);
                }

                Log::info('ProcessPaymentWebhookJob: wallet top-up updated', [
                    'top_up_id' => $topUp->id,
                    'status'    => $status,
                ]);

                return;
            }

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
        return match (strtoupper($providerStatus)) {
            // Xendit invoice statuses
            'PAID', 'SETTLED'                            => 'paid',
            'EXPIRED'                                    => 'failed',
            // Generic
            'SUCCEEDED', 'COMPLETED', 'SUCCESS'          => 'paid',
            'FAILED', 'FAILURE', 'DECLINED'              => 'failed',
            'REFUNDED', 'REVERSED'                       => 'refunded',
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
