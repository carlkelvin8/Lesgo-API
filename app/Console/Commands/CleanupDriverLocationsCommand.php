<?php

namespace App\Console\Commands;

use App\Models\DriverLocation;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CleanupDriverLocationsCommand extends Command
{
    protected $signature = 'driver-locations:cleanup {--days=30 : Delete records older than this many days}';

    protected $description = 'Prune old driver location history to prevent unbounded table growth';

    public function handle(): int
    {
        $days = max(1, (int) $this->option('days'));
        $cutoff = now()->subDays($days);

        $deleted = DriverLocation::where('recorded_at', '<', $cutoff)->delete();

        $this->info("Deleted {$deleted} driver location rows older than {$days} days (before {$cutoff->toDateTimeString()}).");

        // Keep at most 500 historical points per driver beyond retention window edge case
        $overflow = DB::select('
            SELECT driver_id FROM (
                SELECT driver_id, COUNT(*) AS cnt
                FROM driver_locations
                GROUP BY driver_id
                HAVING cnt > 500
            ) AS heavy
        ');

        $trimmed = 0;
        foreach ($overflow as $row) {
            $ids = DriverLocation::where('driver_id', $row->driver_id)
                ->orderByDesc('recorded_at')
                ->skip(500)
                ->pluck('id');

            if ($ids->isNotEmpty()) {
                $trimmed += DriverLocation::whereIn('id', $ids)->delete();
            }
        }

        if ($trimmed > 0) {
            $this->info("Trimmed {$trimmed} overflow rows (cap 500 per driver).");
        }

        return self::SUCCESS;
    }
}
