<?php

namespace App\Jobs;

use App\Models\DailyReport;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
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

        $totalOrders     = Order::whereBetween('created_at', [$start, $end])->count();
        $completedOrders = Order::where('status', 'completed')->whereBetween('updated_at', [$start, $end])->count();
        $cancelledOrders = Order::where('status', 'cancelled')->whereBetween('updated_at', [$start, $end])->count();

        $newUsers   = User::where('role', '!=', 'driver')->whereBetween('created_at', [$start, $end])->count();
        $newDrivers = User::where('role', 'driver')->whereBetween('created_at', [$start, $end])->count();

        $revenue = Payment::where('status', 'paid')
            ->whereBetween('paid_at', [$start, $end])
            ->sum('amount');

        $avgFare = Order::where('status', 'completed')
            ->whereBetween('updated_at', [$start, $end])
            ->avg('actual_fare') ?? 0;

        $totalDistanceM = Order::where('status', 'completed')
            ->whereBetween('updated_at', [$start, $end])
            ->sum('actual_distance_m');

        DailyReport::updateOrCreate(
            ['report_date' => $date],
            [
                'total_orders'      => $totalOrders,
                'completed_orders'  => $completedOrders,
                'cancelled_orders'  => $cancelledOrders,
                'new_users'         => $newUsers,
                'new_drivers'       => $newDrivers,
                'total_revenue'     => round($revenue, 2),
                'avg_fare'          => round($avgFare, 2),
                'total_distance_km' => (int) round($totalDistanceM / 1000),
            ]
        );

        Log::info('GenerateDailyReportJob: report generated', [
            'date'           => $date,
            'total_orders'   => $totalOrders,
            'total_revenue'  => $revenue,
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
