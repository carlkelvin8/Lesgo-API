<?php

namespace App\Jobs;

use App\Models\Order;
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

        // Placeholder: replace with actual mail/SMS/push notification
        Log::channel('stack')->info('Order confirmation sent', [
            'order_id'      => $this->order->id,
            'customer_id'   => $this->order->customer_id,
            'customer_email'=> $this->order->customer?->email,
            'service'       => $this->order->service?->name,
            'estimated_fare'=> $this->order->estimated_fare,
        ]);

        // TODO: Mail::to($this->order->customer)->send(new OrderConfirmationMail($this->order));
        // TODO: SmsService::send($this->order->customer->phone_number, "Your order #{$this->order->id} has been placed.");
    }

    public function failed(\Throwable $e): void
    {
        Log::error('SendOrderConfirmationJob failed', [
            'order_id' => $this->order->id,
            'error'    => $e->getMessage(),
        ]);
    }
}
