<?php

namespace App\Jobs;

use App\Services\SmsService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendSmsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 60;

    public function __construct(
        private string $to,
        private string $message
    ) {
        $this->onQueue('notifications');
    }

    public function handle(SmsService $sms): void
    {
        $sms->send($this->to, $this->message);
    }

    public function failed(\Throwable $e): void
    {
        Log::error('SendSmsJob failed', [
            'to'    => $this->to,
            'error' => $e->getMessage(),
        ]);
    }
}
