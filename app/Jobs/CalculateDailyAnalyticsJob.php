<?php

namespace App\Jobs;

use App\Services\AnalyticsService;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class CalculateDailyAnalyticsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 300; // 5 minutes
    public $tries = 3;

    public function __construct(
        private Carbon $date
    ) {}

    public function handle(AnalyticsService $analyticsService): void
    {
        Log::info('Starting daily analytics calculation', [
            'date' => $this->date->toDateString(),
            'job_id' => $this->job->getJobId(),
        ]);

        try {
            $analyticsService->calculateDailyMetrics($this->date);
            
            Log::info('Daily analytics calculation completed successfully', [
                'date' => $this->date->toDateString(),
            ]);

        } catch (\Exception $e) {
            Log::error('Daily analytics calculation failed', [
                'date' => $this->date->toDateString(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('Daily analytics job failed permanently', [
            'date' => $this->date->toDateString(),
            'error' => $exception->getMessage(),
            'attempts' => $this->attempts(),
        ]);
    }
}