<?php

namespace App\Console\Commands;

use App\Jobs\CalculateDailyAnalyticsJob;
use Carbon\Carbon;
use Illuminate\Console\Command;

class CalculateAnalyticsCommand extends Command
{
    protected $signature = 'analytics:calculate 
                           {--date= : Date to calculate analytics for (YYYY-MM-DD)}
                           {--days= : Number of days to calculate (default: 1)}
                           {--queue : Queue the calculation jobs}';

    protected $description = 'Calculate daily analytics metrics';

    public function handle(): int
    {
        $date = $this->option('date') ? Carbon::parse($this->option('date')) : Carbon::yesterday();
        $days = (int) $this->option('days', 1);
        $useQueue = $this->option('queue');

        $this->info("Calculating analytics for {$days} day(s) starting from {$date->toDateString()}");

        $bar = $this->output->createProgressBar($days);
        $bar->start();

        for ($i = 0; $i < $days; $i++) {
            $currentDate = $date->copy()->subDays($i);
            
            if ($useQueue) {
                CalculateDailyAnalyticsJob::dispatch($currentDate);
                $this->line(" Queued analytics calculation for {$currentDate->toDateString()}");
            } else {
                try {
                    app(\App\Services\AnalyticsService::class)->calculateDailyMetrics($currentDate);
                    $this->line(" ✓ Calculated analytics for {$currentDate->toDateString()}");
                } catch (\Exception $e) {
                    $this->error(" ✗ Failed to calculate analytics for {$currentDate->toDateString()}: {$e->getMessage()}");
                }
            }
            
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();

        if ($useQueue) {
            $this->info("Analytics calculation jobs have been queued successfully.");
        } else {
            $this->info("Analytics calculation completed.");
        }

        return self::SUCCESS;
    }
}