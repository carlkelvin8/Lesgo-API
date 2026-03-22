<?php

namespace App\Jobs;

use App\Models\Order;
use App\Services\NotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendOrderConfirmationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 30;

    public function __construct(public Order $order) {}

    public function handle(): void
    {
        $this->order->loadMissing(['customer', 'service']);

        $customer = $this->order->customer;
        if (!$customer) return;

        NotificationService::send(
            user: $customer,
            type: 'order.confirmed',
            title: 'Order Confirmed',
            body: "Your order #{$this->order->id} for {$this->order->service?->name} has been placed. Estimated fare: ₱{$this->order->estimated_fare}.",
            data: [
                'order_id'       => $this->order->id,
                'estimated_fare' => $this->order->estimated_fare,
                'service'        => $this->order->service?->name,
            ],
            channel: 'push'
        );

        Log::info('SendOrderConfirmationJob: notification sent', [
            'order_id'    => $this->order->id,
            'customer_id' => $customer->id,
        ]);
    }

    public function failed(\Throwable $e): void
    {
        Log::error('SendOrderConfirmationJob failed', [
            'order_id' => $this->order->id,
            'error'    => $e->getMessage(),
        ]);
    }
}
