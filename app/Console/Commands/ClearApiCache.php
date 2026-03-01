<?php

namespace App\Console\Commands;

use App\Services\CacheService;
use Illuminate\Console\Command;

class ClearApiCache extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cache:clear-api {--type=all : Type of cache to clear (all, services, orders, user)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clear API cache by type';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $type = $this->option('type');

        $this->info("Clearing {$type} cache...");

        match ($type) {
            'services' => CacheService::clearServiceCache(),
            'orders' => CacheService::clearOrderCache(),
            'all' => CacheService::clearAll(),
            default => $this->error("Invalid cache type: {$type}")
        };

        $this->info('Cache cleared successfully!');

        // Show cache stats
        $stats = CacheService::getStats();
        $this->table(
            ['Metric', 'Value'],
            collect($stats)->map(fn($value, $key) => [$key, $value])->values()
        );

        return Command::SUCCESS;
    }
}
