<?php

namespace App\Jobs;

use App\Models\DailyReport;
use App\Models\Payment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class GenerateDailyReportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public function __construct(private ?string $date = null) {}

    public function handle(): void
    {
        $date = $this->date ?? now()->subDay()->toDateString();

        $start = "{$date} 00:00:00";
        $end   = "{$date} 23:59:59";

        // Consolidate order stats into a single query
        $orderStats = \Illuminate\Support\Facades\DB::selectOne("
            SELECT
                COUNT(*) AS total_orders,
                SUM(CASE WHEN status = 'completed' AND updated_at BETWEEN ? AND ? THEN 1 ELSE 0 END) AS completed_orders,
                SUM(CASE WHEN status = 'cancelled' AND updated_at BETWEEN ? AND ? THEN 1 ELSE 0 END) AS cancelled_orders,
                AVG(CASE WHEN status = 'completed' AND updated_at BETWEEN ? AND ? THEN actual_fare ELSE NULL END) AS avg_fare,
                SUM(CASE WHEN status = 'completed' AND updated_at BETWEEN ? AND ? THEN actual_distance_m ELSE 0 END) AS total_distance_m
            FROM orders
            WHERE created_at BETWEEN ? AND ?
        ", [$start, $end, $start, $end, $start, $end, $start, $end, $start, $end]);

        // Consolidate user stats into a single query
        $userStats = \Illuminate\Support\Facades\DB::selectOne("
            SELECT
                SUM(CASE WHEN role != 'driver' THEN 1 ELSE 0 END) AS new_users,
                SUM(CASE WHEN role = 'driver' THEN 1 ELSE 0 END) AS new_drivers
            FROM users
            WHERE created_at BETWEEN ? AND ?
        ", [$start, $end]);

        $revenue = Payment::where('status', 'paid')
            ->whereBetween('paid_at', [$start, $end])
            ->sum('amount');

        DailyReport::updateOrCreate(
            ['report_date' => $date],
            [
                'total_orders'      => (int) ($orderStats->total_orders ?? 0),
                'completed_orders'  => (int) ($orderStats->completed_orders ?? 0),
                'cancelled_orders'  => (int) ($orderStats->cancelled_orders ?? 0),
                'new_users'         => (int) ($userStats->new_users ?? 0),
                'new_drivers'       => (int) ($userStats->new_drivers ?? 0),
                'total_revenue'     => round((float) $revenue, 2),
                'avg_fare'          => round((float) ($orderStats->avg_fare ?? 0), 2),
                'total_distance_km' => (int) round(($orderStats->total_distance_m ?? 0) / 1000),
            ]
        );

        Log::info('GenerateDailyReportJob: report generated', [
            'date'          => $date,
            'total_orders'  => $orderStats->total_orders ?? 0,
            'total_revenue' => $revenue,
        ]);
    }

    public function failed(\Throwable $e): void
    {
        Log::error('GenerateDailyReportJob failed', [
            'date'  => $this->date,
            'error' => $e->getMessage(),
        ]);
    }
}
