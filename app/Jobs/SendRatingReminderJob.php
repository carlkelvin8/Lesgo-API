<?php

namespace App\Jobs;

use App\Models\Order;
use App\Models\User;
use App\Services\FcmService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendRatingReminderJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 30;

    public function __construct(
        private int $customerId,
        private int $orderId,
    ) {
        $this->onQueue('notifications');
    }

    public function handle(FcmService $fcm): void
    {
        $user = User::find($this->customerId);

        if (!$user) {
            Log::warning('SendRatingReminderJob: customer not found', ['user_id' => $this->customerId]);
            return;
        }

        if (!$user->fcm_token) {
            Log::info('SendRatingReminderJob: no FCM token, skipping', ['user_id' => $this->customerId]);
            return;
        }

        $order = Order::find($this->orderId);
        $riderName = 'your rider';
        if ($order && $order->driver) {
            $riderName = $order->driver->name ?? 'your rider';
        }

        $title = 'How was your delivery?';
        $body = "Rate your experience with $riderName";
        $data = [
            'type' => 'order_rating_reminder',
            'order_id' => (string) $this->orderId,
            'route' => 'rate_order',
        ];

        $sent = $fcm->sendToToken($user->fcm_token, $title, $body, $data);

        if ($sent) {
            Log::info('Rating reminder sent', [
                'user_id' => $this->customerId,
                'order_id' => $this->orderId,
            ]);
        }
    }

    public function failed(\Throwable $e): void
    {
        Log::error('SendRatingReminderJob failed', [
            'customer_id' => $this->customerId,
            'order_id'    => $this->orderId,
            'error'       => $e->getMessage(),
        ]);
    }
}
