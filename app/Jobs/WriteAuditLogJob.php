<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class WriteAuditLogJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 10;

    public function __construct(private array $entry) {}

    public function handle(): void
    {
        DB::table('audit_logs')->insert($this->entry);
    }

    public function failed(\Throwable $e): void
    {
        // Last resort — write to log file if DB insert keeps failing
        Log::error('WriteAuditLogJob failed — audit entry lost', [
            'entry' => $this->entry,
            'error' => $e->getMessage(),
        ]);
    }
}
