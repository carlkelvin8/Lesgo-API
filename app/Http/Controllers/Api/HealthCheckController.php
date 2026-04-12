<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;

class HealthCheckController extends Controller
{
    /**
     * Comprehensive health check
     */
    public function __invoke(): JsonResponse
    {
        $checks = [
            'database' => $this->checkDatabase(),
            'redis' => $this->checkRedis(),
            'queue' => $this->checkQueue(),
            'storage' => $this->checkStorage(),
            'cache' => $this->checkCache(),
            'logging' => $this->checkLogging(),
            'external_services' => $this->checkExternalServices(),
        ];

        // Determine overall health
        $healthy = collect($checks)->every(fn($check) => $check['status'] === 'healthy');
        $degraded = collect($checks)->contains(fn($check) => $check['status'] === 'degraded');

        $overallStatus = $healthy ? 'healthy' : ($degraded ? 'degraded' : 'unhealthy');
        $httpCode = $healthy ? 200 : ($degraded ? 200 : 503);

        return response()->json([
            'status' => $overallStatus,
            'timestamp' => now()->toISOString(),
            'version' => config('app.version', '1.0.0'),
            'environment' => app()->environment(),
            'uptime_seconds' => $this->getUptime(),
            'checks' => $checks,
        ], $httpCode);
    }

    /**
     * Database health check
     */
    private function checkDatabase(): array
    {
        try {
            $start = microtime(true);
            DB::connection()->getPdo();
            $duration = (microtime(true) - $start) * 1000;

            // Check connection pool
            $connections = DB::connection()->getPdo()->getAttribute(\PDO::ATTR_CONNECTION_STATUS);

            // Run a simple query
            DB::select('SELECT 1');

            return [
                'status' => 'healthy',
                'connection' => config('database.default'),
                'host' => config('database.connections.' . config('database.default') . '.host'),
                'duration_ms' => round($duration, 2),
                'connections' => $connections,
                'last_check' => now()->toISOString(),
            ];
        } catch (\Exception $e) {
            Log::error('Health check: Database unhealthy', ['error' => $e->getMessage()]);

            return [
                'status' => 'unhealthy',
                'error' => $e->getMessage(),
                'last_check' => now()->toISOString(),
            ];
        }
    }

    /**
     * Redis health check
     */
    private function checkRedis(): array
    {
        try {
            $start = microtime(true);
            
            // Test Redis connection
            if (config('cache.default') === 'redis' || config('queue.default') === 'redis') {
                Cache::store('redis')->put('health_check', 'ok', 10);
                $value = Cache::store('redis')->get('health_check');
                
                if ($value !== 'ok') {
                    throw new \Exception('Redis read/write mismatch');
                }
            }

            $duration = (microtime(true) - $start) * 1000;

            // Get Redis info
            $info = null;
            try {
                $info = Redis::info();
            } catch (\Exception $e) {
                // Info command might be disabled
            }

            return [
                'status' => 'healthy',
                'driver' => config('cache.default'),
                'duration_ms' => round($duration, 2),
                'used_memory' => $info['used_memory_human'] ?? null,
                'connected_clients' => $info['connected_clients'] ?? null,
                'last_check' => now()->toISOString(),
            ];
        } catch (\Exception $e) {
            return [
                'status' => config('cache.default') === 'redis' ? 'unhealthy' : 'healthy',
                'message' => 'Redis not configured or not responding',
                'error' => config('cache.default') === 'redis' ? $e->getMessage() : null,
                'last_check' => now()->toISOString(),
            ];
        }
    }

    /**
     * Queue health check
     */
    private function checkQueue(): array
    {
        try {
            $queueConnection = config('queue.default');
            
            // Check for failed jobs
            $failedJobsCount = DB::table('failed_jobs')
                ->where('failed_at', '>=', now()->subHour())
                ->count();

            // Check job counts by queue
            $queueSizes = [];
            if ($queueConnection === 'database') {
                $queues = ['critical', 'high', 'medium', 'low', 'default'];
                foreach ($queues as $queue) {
                    $count = DB::table('jobs')
                        ->where('queue', $queue)
                        ->count();
                    $queueSizes[$queue] = $count;
                }
            }

            $status = $failedJobsCount > 100 ? 'degraded' : 'healthy';

            return [
                'status' => $status,
                'connection' => $queueConnection,
                'failed_jobs_last_hour' => $failedJobsCount,
                'queue_sizes' => $queueSizes,
                'last_check' => now()->toISOString(),
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'unhealthy',
                'error' => $e->getMessage(),
                'last_check' => now()->toISOString(),
            ];
        }
    }

    /**
     * Storage health check
     */
    private function checkStorage(): array
    {
        try {
            $disks = ['local', 'public'];
            $diskStatus = [];

            foreach ($disks as $disk) {
                $start = microtime(true);
                $healthy = Storage::disk($disk)->exists('.gitkeep') || Storage::disk($disk)->put('.health_check', 'ok');
                $duration = (microtime(true) - $start) * 1000;

                if ($healthy && Storage::disk($disk)->exists('.health_check')) {
                    Storage::disk($disk)->delete('.health_check');
                }

                $diskStatus[$disk] = [
                    'status' => $healthy ? 'healthy' : 'degraded',
                    'duration_ms' => round($duration, 2),
                ];
            }

            // Check disk space
            $freeSpace = disk_free_space(storage_path());
            $totalSpace = disk_total_space(storage_path());
            $usedPercent = $totalSpace > 0 ? round((1 - $freeSpace / $totalSpace) * 100, 2) : 0;

            $status = $usedPercent > 90 ? 'degraded' : 'healthy';

            return [
                'status' => $status,
                'disks' => $diskStatus,
                'storage_used_percent' => $usedPercent,
                'free_space_gb' => round($freeSpace / 1024 / 1024 / 1024, 2),
                'last_check' => now()->toISOString(),
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'unhealthy',
                'error' => $e->getMessage(),
                'last_check' => now()->toISOString(),
            ];
        }
    }

    /**
     * Cache health check
     */
    private function checkCache(): array
    {
        try {
            $start = microtime(true);
            
            $cacheDriver = config('cache.default');
            Cache::put('health_check', 'ok', 10);
            $value = Cache::get('health_check');
            Cache::forget('health_check');

            $duration = (microtime(true) - $start) * 1000;

            if ($value !== 'ok') {
                throw new \Exception('Cache read/write mismatch');
            }

            return [
                'status' => 'healthy',
                'driver' => $cacheDriver,
                'duration_ms' => round($duration, 2),
                'last_check' => now()->toISOString(),
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'unhealthy',
                'error' => $e->getMessage(),
                'last_check' => now()->toISOString(),
            ];
        }
    }

    /**
     * Logging health check
     */
    private function checkLogging(): array
    {
        try {
            $logPath = storage_path('logs');
            $isWritable = is_writable($logPath);
            $logSize = $this->getLogDirectorySize();

            return [
                'status' => $isWritable ? 'healthy' : 'degraded',
                'log_path' => $logPath,
                'writable' => $isWritable,
                'size_mb' => round($logSize / 1024 / 1024, 2),
                'last_check' => now()->toISOString(),
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'unhealthy',
                'error' => $e->getMessage(),
                'last_check' => now()->toISOString(),
            ];
        }
    }

    /**
     * External services health check
     */
    private function checkExternalServices(): array
    {
        $services = [];

        // Check Xendit (payment gateway)
        if (config('services.xendit.secret_key')) {
            try {
                $start = microtime(true);
                $response = Http::withToken(config('services.xendit.secret_key'))
                    ->timeout(5)
                    ->get('https://api.xendit.co/balance');
                
                $services['xendit'] = [
                    'status' => $response->successful() ? 'healthy' : 'degraded',
                    'duration_ms' => round((microtime(true) - $start) * 1000, 2),
                    'status_code' => $response->status(),
                ];
            } catch (\Exception $e) {
                $services['xendit'] = [
                    'status' => 'degraded',
                    'error' => $e->getMessage(),
                ];
            }
        }

        // Check FCM (push notifications)
        if (config('services.firebase.credentials')) {
            $services['firebase_fcm'] = [
                'status' => 'healthy',
                'message' => 'Credentials configured',
            ];
        }

        // Check CDN
        if (config('cdn.base_url')) {
            try {
                $start = microtime(true);
                $response = Http::timeout(5)->get(config('cdn.base_url'));
                
                $services['cdn'] = [
                    'status' => $response->successful() ? 'healthy' : 'degraded',
                    'duration_ms' => round((microtime(true) - $start) * 1000, 2),
                ];
            } catch (\Exception $e) {
                $services['cdn'] = [
                    'status' => 'degraded',
                    'error' => $e->getMessage(),
                ];
            }
        }

        return [
            'status' => collect($services)->every(fn($s) => $s['status'] === 'healthy') ? 'healthy' : 'degraded',
            'services' => $services,
            'last_check' => now()->toISOString(),
        ];
    }

    /**
     * Get application uptime
     */
    private function getUptime(): int
    {
        // Try to get uptime from /proc/uptime (Linux)
        if (file_exists('/proc/uptime')) {
            return (int) file_get_contents('/proc/uptime');
        }

        // Fallback: time since oldest log file
        $logFiles = glob(storage_path('logs/*.log'));
        if (!empty($logFiles)) {
            $oldestLog = min(array_map('filemtime', $logFiles));
            return time() - $oldestLog;
        }

        return 0;
    }

    /**
     * Get log directory size
     */
    private function getLogDirectorySize(): int
    {
        $logPath = storage_path('logs');
        $size = 0;

        if (!is_dir($logPath)) {
            return 0;
        }

        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($logPath)) as $file) {
            if ($file->isFile()) {
                $size += $file->getSize();
            }
        }

        return $size;
    }

    /**
     * Simple ping endpoint (lightweight)
     */
    public function ping(): JsonResponse
    {
        return response()->json([
            'status' => 'ok',
            'message' => 'LeSGo API v1 OK',
            'timestamp' => now()->toISOString(),
            'environment' => app()->environment(),
            'php_version' => PHP_VERSION,
            'laravel_version' => app()->version(),
        ]);
    }

    /**
     * Detailed readiness check (for Kubernetes/load balancers)
     */
    public function readiness(): JsonResponse
    {
        $dbHealthy = true;
        $cacheHealthy = true;

        try {
            DB::connection()->getPdo();
        } catch (\Exception $e) {
            $dbHealthy = false;
        }

        try {
            Cache::put('readiness_check', 'ok', 10);
            $cacheHealthy = Cache::get('readiness_check') === 'ok';
        } catch (\Exception $e) {
            $cacheHealthy = false;
        }

        $ready = $dbHealthy && $cacheHealthy;

        return response()->json([
            'ready' => $ready,
            'database' => $dbHealthy ? 'connected' : 'disconnected',
            'cache' => $cacheHealthy ? 'connected' : 'disconnected',
            'timestamp' => now()->toISOString(),
        ], $ready ? 200 : 503);
    }

    /**
     * Liveness check (is the application running?)
     */
    public function liveness(): JsonResponse
    {
        return response()->json([
            'alive' => true,
            'timestamp' => now()->toISOString(),
            'version' => config('app.version', '1.0.0'),
        ]);
    }
}
