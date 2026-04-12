<?php

namespace App\Services;

use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Bus\Batch;
use Illuminate\Support\Facades\Bus;

/**
 * Queue Optimization Service
 * 
 * Provides intelligent queue management with priority routing,
 * job batching, retry logic, and performance monitoring.
 */
class QueueService
{
    /**
     * Queue priorities (lower number = higher priority)
     */
    private const QUEUE_PRIORITIES = [
        'critical' => 1,    // Payment processing, security events
        'high' => 2,        // Order notifications, SMS
        'medium' => 3,      // Email notifications, analytics
        'low' => 4,         // Reports, cleanup tasks
    ];

    /**
     * Queue to connection mapping
     */
    private const QUEUE_CONNECTIONS = [
        'critical' => 'redis',  // Fastest connection for critical jobs
        'high' => 'redis',
        'medium' => 'database',
        'low' => 'database',
    ];

    /**
     * Dispatch job with intelligent queue routing
     */
    public function dispatchJob(object $job, string $priority = 'medium', array $options = []): void
    {
        $queue = $this->determineQueue($priority, $options);
        
        // Set job properties
        if (method_exists($job, 'onQueue')) {
            $job->onQueue($queue);
        }

        // Set connection if specified
        $connection = self::QUEUE_CONNECTIONS[$priority] ?? 'database';
        
        // Dispatch with options
        if (!empty($options['delay'])) {
            $job->delay($options['delay']);
        }

        if (!empty($options['tries'])) {
            $job->tries = $options['tries'];
        }

        if (!empty($options['backoff'])) {
            $job->backoff = $options['backoff'];
        }

        dispatch($job)->onConnection($connection);

        Log::info('Job dispatched', [
            'job' => get_class($job),
            'queue' => $queue,
            'priority' => $priority,
            'connection' => $connection,
        ]);
    }

    /**
     * Dispatch job batch with tracking
     */
    public function dispatchBatch(array $jobs, string $batchName, callable $callback = null): Batch
    {
        $batch = Bus::batch($jobs)
            ->name($batchName)
            ->then(function (Batch $batch) use ($callback) {
                Log::info("Batch completed: {$batchName}", [
                    'batch_id' => $batch->id,
                    'total_jobs' => $batch->totalJobs,
                    'failed_jobs' => $batch->failedJobs,
                ]);

                if ($callback) {
                    $callback($batch);
                }
            })
            ->catch(function (Batch $batch, \Throwable $e) {
                Log::error("Batch job failed: {$batch->name}", [
                    'batch_id' => $batch->id,
                    'error' => $e->getMessage(),
                ]);
            })
            ->finally(function (Batch $batch) {
                Log::info("Batch finished: {$batch->name}", [
                    'batch_id' => $batch->id,
                    'status' => $batch->cancelled() ? 'cancelled' : 'completed',
                ]);
            })
            ->dispatch();

        return $batch;
    }

    /**
     * Dispatch job to chain (sequential execution)
     */
    public function dispatchChain(array $jobs): void
    {
        Bus::chain($jobs)
            ->catch(function (\Throwable $e) {
                Log::error('Job chain failed', [
                    'error' => $e->getMessage(),
                ]);
            })
            ->dispatch();
    }

    /**
     * Determine optimal queue based on priority and load
     */
    private function determineQueue(string $priority, array $options = []): string
    {
        // Allow override via options
        if (!empty($options['queue'])) {
            return $options['queue'];
        }

        $baseQueue = $priority;

        // Check queue load and distribute if overloaded
        $queueSize = Cache::get("queue_size:{$baseQueue}", 0);
        $maxQueueSize = $this->getMaxQueueSize($baseQueue);

        if ($queueSize > $maxQueueSize) {
            // Route to overflow queue
            $baseQueue = "{$baseQueue}_overflow";
            Log::warning("Queue overflow, routing to {$baseQueue}", [
                'original_queue' => $priority,
                'current_size' => $queueSize,
                'max_size' => $maxQueueSize,
            ]);
        }

        // Increment queue size counter
        Cache::increment("queue_size:{$baseQueue}");
        Cache::put("queue_size:{$baseQueue}", $queueSize + 1, now()->addMinutes(5));

        return $baseQueue;
    }

    /**
     * Get maximum queue size before overflow
     */
    private function getMaxQueueSize(string $queue): int
    {
        return match($queue) {
            'critical' => 100,
            'high' => 500,
            'medium' => 1000,
            'low' => 5000,
            default => 1000,
        };
    }

    /**
     * Get queue health metrics
     */
    public function getQueueHealth(): array
    {
        $queues = ['critical', 'high', 'medium', 'low'];
        $health = [];

        foreach ($queues as $queue) {
            $size = Cache::get("queue_size:{$queue}", 0);
            $maxSize = $this->getMaxQueueSize($queue);
            $utilization = $maxSize > 0 ? round(($size / $maxSize) * 100, 2) : 0;

            $health[$queue] = [
                'size' => $size,
                'max_size' => $maxSize,
                'utilization_percent' => $utilization,
                'status' => $utilization > 90 ? 'critical' : ($utilization > 70 ? 'warning' : 'healthy'),
                'connection' => self::QUEUE_CONNECTIONS[$queue] ?? 'database',
            ];
        }

        // Get failed jobs count
        $failedJobs = \Illuminate\Support\Facades\DB::table('failed_jobs')
            ->where('failed_at', '>=', now()->subHour())
            ->count();

        $health['failed_jobs_last_hour'] = $failedJobs;
        $health['overall_status'] = $this->getOverallStatus($health);

        return $health;
    }

    /**
     * Retry failed jobs
     */
    public function retryFailedJobs(?int $limit = 10): int
    {
        $failedJobs = \Illuminate\Support\Facades\DB::table('failed_jobs')
            ->orderBy('failed_at', 'desc')
            ->limit($limit)
            ->get();

        $retried = 0;

        foreach ($failedJobs as $failedJob) {
            try {
                // Deserialize and retry job
                $job = unserialize($failedJob->payload);
                dispatch($job);

                // Remove from failed jobs table
                \Illuminate\Support\Facades\DB::table('failed_jobs')
                    ->where('id', $failedJob->id)
                    ->delete();

                $retried++;

                Log::info('Retried failed job', [
                    'failed_job_id' => $failedJob->id,
                    'job' => get_class($job),
                ]);
            } catch (\Throwable $e) {
                Log::error('Failed to retry job', [
                    'failed_job_id' => $failedJob->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $retried;
    }

    /**
     * Clear old failed jobs (older than specified days)
     */
    public function clearOldFailedJobs(int $daysOld = 7): int
    {
        $count = \Illuminate\Support\Facades\DB::table('failed_jobs')
            ->where('failed_at', '<', now()->subDays($daysOld))
            ->delete();

        Log::info('Cleared old failed jobs', [
            'count' => $count,
            'days_old' => $daysOld,
        ]);

        return $count;
    }

    /**
     * Get overall queue status
     */
    private function getOverallStatus(array $health): string
    {
        $statuses = array_column($health, 'status');
        
        if (in_array('critical', $statuses)) {
            return 'critical';
        }

        if (in_array('warning', $statuses)) {
            return 'warning';
        }

        return 'healthy';
    }

    /**
     * Get queue statistics for monitoring
     */
    public function getQueueStatistics(): array
    {
        return [
            'queues' => $this->getQueueHealth(),
            'workers' => $this->getWorkerCount(),
            'throughput' => $this->getThroughputStats(),
        ];
    }

    /**
     * Get active worker count
     */
    private function getWorkerCount(): array
    {
        // This would integrate with Supervisor or process manager
        // For now, return cached counts
        return [
            'critical_workers' => Cache::get('workers:critical', 2),
            'high_workers' => Cache::get('workers:high', 2),
            'medium_workers' => Cache::get('workers:medium', 3),
            'low_workers' => Cache::get('workers:low', 2),
        ];
    }

    /**
     * Get throughput statistics
     */
    private function getThroughputStats(): array
    {
        return [
            'jobs_per_minute' => Cache::get('throughput:jobs_per_minute', 0),
            'average_processing_time' => Cache::get('throughput:avg_processing_time', 0),
            'success_rate' => Cache::get('throughput:success_rate', 100),
        ];
    }

    /**
     * Track job completion for metrics
     */
    public function trackJobCompletion(string $queue, float $processingTime, bool $success): void
    {
        // Update throughput metrics
        $currentRate = Cache::get('throughput:jobs_per_minute', 0);
        Cache::put('throughput:jobs_per_minute', $currentRate + 1, now()->addMinute());

        // Update average processing time
        $avgTime = Cache::get('throughput:avg_processing_time', 0);
        $newAvg = $avgTime > 0 ? ($avgTime + $processingTime) / 2 : $processingTime;
        Cache::put('throughput:avg_processing_time', $newAvg, now()->addHour());

        // Update success rate
        $totalKey = 'throughput:total_jobs';
        $successKey = 'throughput:successful_jobs';

        $total = Cache::get($totalKey, 0) + 1;
        Cache::put($totalKey, $total, now()->addHour());

        if ($success) {
            $successCount = Cache::get($successKey, 0) + 1;
            Cache::put($successKey, $successCount, now()->addHour());
        }

        $successRate = $total > 0 ? round((Cache::get($successKey, 0) / $total) * 100, 2) : 100;
        Cache::put('throughput:success_rate', $successRate, now()->addHour());
    }
}
