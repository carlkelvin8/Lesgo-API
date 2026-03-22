<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MonitorFailedJobs extends Command
{
    protected $signature   = 'queue:monitor-failed {--threshold=5 : Alert if failed jobs exceed this count}';
    protected $description = 'Check failed jobs table and alert if threshold exceeded';

    public function handle(): int
    {
        $threshold = (int) $this->option('threshold');

        $recentFailed = DB::table('failed_jobs')
            ->where('failed_at', '>=', now()->subHour())
            ->count();

        $totalFailed = DB::table('failed_jobs')->count();

        if ($recentFailed >= $threshold) {
            Log::warning('MonitorFailedJobs: threshold exceeded', [
                'recent_failed_1h' => $recentFailed,
                'total_failed'     => $totalFailed,
                'threshold'        => $threshold,
            ]);

            $this->error("ALERT: {$recentFailed} jobs failed in the last hour (threshold: {$threshold})");
            $this->table(
                ['Queue', 'Job', 'Failed At'],
                DB::table('failed_jobs')
                    ->where('failed_at', '>=', now()->subHour())
                    ->orderByDesc('failed_at')
                    ->limit(10)
                    ->get(['queue', 'payload', 'failed_at'])
                    ->map(fn ($row) => [
                        $row->queue,
                        json_decode($row->payload, true)['displayName'] ?? 'Unknown',
                        $row->failed_at,
                    ])
                    ->toArray()
            );

            return self::FAILURE;
        }

        $this->info("Failed jobs OK — {$recentFailed} in last hour, {$totalFailed} total.");

        Log::info('MonitorFailedJobs: check passed', [
            'recent_failed_1h' => $recentFailed,
            'total_failed'     => $totalFailed,
        ]);

        return self::SUCCESS;
    }
}
