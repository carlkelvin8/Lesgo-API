<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class DriverPerformanceMetric extends Model
{
    use HasFactory;

    protected $fillable = [
        'driver_id',
        'date',
        'total_orders',
        'completed_orders',
        'cancelled_orders',
        'total_revenue',
        'total_distance_km',
        'online_minutes',
        'average_rating',
        'total_ratings',
        'acceptance_rate',
        'completion_rate',
        'average_delivery_time',
        'customer_complaints',
        'performance_data',
    ];

    protected $casts = [
        'date' => 'date',
        'total_revenue' => 'decimal:2',
        'total_distance_km' => 'decimal:2',
        'average_rating' => 'decimal:2',
        'acceptance_rate' => 'decimal:2',
        'completion_rate' => 'decimal:2',
        'average_delivery_time' => 'decimal:2',
        'performance_data' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // Relationships

    public function driver()
    {
        return $this->belongsTo(User::class, 'driver_id');
    }

    // Scopes

    public function scopeForDriver($query, int $driverId)
    {
        return $query->where('driver_id', $driverId);
    }

    public function scopeInDateRange($query, Carbon $startDate, Carbon $endDate)
    {
        return $query->whereBetween('date', [$startDate, $endDate]);
    }

    public function scopeTopPerformers($query, int $limit = 10)
    {
        return $query->orderByDesc('completion_rate')
                    ->orderByDesc('average_rating')
                    ->orderByDesc('total_revenue')
                    ->limit($limit);
    }

    public function scopeActiveDrivers($query)
    {
        return $query->where('total_orders', '>', 0);
    }

    public function scopeHighRated($query, float $minRating = 4.0)
    {
        return $query->where('average_rating', '>=', $minRating);
    }

    // Helper methods

    public function calculatePerformanceScore(): float
    {
        $weights = [
            'completion_rate' => 0.3,
            'acceptance_rate' => 0.2,
            'average_rating' => 0.25,
            'revenue_per_hour' => 0.15,
            'customer_satisfaction' => 0.1,
        ];

        $scores = [
            'completion_rate' => min($this->completion_rate / 100, 1),
            'acceptance_rate' => min($this->acceptance_rate / 100, 1),
            'average_rating' => $this->average_rating ? ($this->average_rating / 5) : 0,
            'revenue_per_hour' => $this->getRevenuePerHour() / 1000, // normalized
            'customer_satisfaction' => max(0, 1 - ($this->customer_complaints / max($this->completed_orders, 1))),
        ];

        $totalScore = 0;
        foreach ($weights as $metric => $weight) {
            $totalScore += ($scores[$metric] ?? 0) * $weight;
        }

        return round($totalScore * 100, 2);
    }

    public function getRevenuePerHour(): float
    {
        if ($this->online_minutes == 0) {
            return 0;
        }

        return ($this->total_revenue / $this->online_minutes) * 60;
    }

    public function getOrdersPerHour(): float
    {
        if ($this->online_minutes == 0) {
            return 0;
        }

        return ($this->completed_orders / $this->online_minutes) * 60;
    }

    public function getEfficiencyRating(): string
    {
        $score = $this->calculatePerformanceScore();

        if ($score >= 90) return 'Excellent';
        if ($score >= 80) return 'Very Good';
        if ($score >= 70) return 'Good';
        if ($score >= 60) return 'Average';
        return 'Needs Improvement';
    }

    public function isTopPerformer(): bool
    {
        return $this->calculatePerformanceScore() >= 85 &&
               $this->completion_rate >= 90 &&
               $this->average_rating >= 4.5;
    }

    public function getFormattedRevenue(): string
    {
        return '₱' . number_format($this->total_revenue, 2);
    }

    public function getFormattedDistance(): string
    {
        return number_format($this->total_distance_km, 1) . ' km';
    }

    public function getFormattedOnlineTime(): string
    {
        $hours = floor($this->online_minutes / 60);
        $minutes = $this->online_minutes % 60;
        
        return sprintf('%dh %dm', $hours, $minutes);
    }

    public static function calculateForDriver(User $driver, Carbon $date): self
    {
        // This would be called by a job to calculate daily metrics
        $orders = Order::where('driver_id', $driver->id)
            ->whereDate('created_at', $date)
            ->get();

        $totalOrders = $orders->count();
        $completedOrders = $orders->where('status', 'completed')->count();
        $cancelledOrders = $orders->where('status', 'cancelled')->count();
        $totalRevenue = $orders->where('status', 'completed')->sum('driver_share');

        // Calculate other metrics...
        $acceptanceRate = $totalOrders > 0 ? ($completedOrders / $totalOrders) * 100 : 0;
        $completionRate = $totalOrders > 0 ? ($completedOrders / $totalOrders) * 100 : 0;

        return self::updateOrCreate(
            ['driver_id' => $driver->id, 'date' => $date],
            [
                'total_orders' => $totalOrders,
                'completed_orders' => $completedOrders,
                'cancelled_orders' => $cancelledOrders,
                'total_revenue' => $totalRevenue,
                'acceptance_rate' => $acceptanceRate,
                'completion_rate' => $completionRate,
                // ... other calculated fields
            ]
        );
    }
}