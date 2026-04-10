<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class ServiceDemandMetric extends Model
{
    use HasFactory;

    protected $fillable = [
        'service_id',
        'date',
        'hour_of_day',
        'day_of_week',
        'total_requests',
        'completed_requests',
        'cancelled_requests',
        'total_revenue',
        'average_wait_time',
        'average_completion_time',
        'peak_demand_score',
        'supply_demand_ratio',
        'demand_data',
    ];

    protected $casts = [
        'date' => 'date',
        'total_revenue' => 'decimal:2',
        'average_wait_time' => 'decimal:2',
        'average_completion_time' => 'decimal:2',
        'supply_demand_ratio' => 'decimal:2',
        'demand_data' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // Relationships

    public function service()
    {
        return $this->belongsTo(Service::class);
    }

    // Scopes

    public function scopeForService($query, int $serviceId)
    {
        return $query->where('service_id', $serviceId);
    }

    public function scopeForHour($query, int $hour)
    {
        return $query->where('hour_of_day', $hour);
    }

    public function scopeForDayOfWeek($query, int $dayOfWeek)
    {
        return $query->where('day_of_week', $dayOfWeek);
    }

    public function scopePeakHours($query, int $minScore = 70)
    {
        return $query->where('peak_demand_score', '>=', $minScore);
    }

    public function scopeHighDemand($query)
    {
        return $query->where('total_requests', '>', 10);
    }

    // Helper methods

    public function getCompletionRate(): float
    {
        if ($this->total_requests == 0) {
            return 0;
        }

        return ($this->completed_requests / $this->total_requests) * 100;
    }

    public function getCancellationRate(): float
    {
        if ($this->total_requests == 0) {
            return 0;
        }

        return ($this->cancelled_requests / $this->total_requests) * 100;
    }

    public function getDemandLevel(): string
    {
        if ($this->peak_demand_score >= 90) return 'Very High';
        if ($this->peak_demand_score >= 70) return 'High';
        if ($this->peak_demand_score >= 50) return 'Medium';
        if ($this->peak_demand_score >= 30) return 'Low';
        return 'Very Low';
    }

    public function getSupplyStatus(): string
    {
        if ($this->supply_demand_ratio >= 1.5) return 'Oversupply';
        if ($this->supply_demand_ratio >= 1.0) return 'Balanced';
        if ($this->supply_demand_ratio >= 0.7) return 'Tight';
        return 'Undersupply';
    }

    public function getFormattedRevenue(): string
    {
        return '₱' . number_format($this->total_revenue, 2);
    }

    public function getFormattedWaitTime(): string
    {
        if ($this->average_wait_time < 60) {
            return number_format($this->average_wait_time, 1) . ' min';
        }

        $hours = floor($this->average_wait_time / 60);
        $minutes = $this->average_wait_time % 60;
        
        return sprintf('%dh %dm', $hours, $minutes);
    }

    public function isPeakHour(): bool
    {
        return $this->peak_demand_score >= 70;
    }

    public function getHourLabel(): string
    {
        return sprintf('%02d:00', $this->hour_of_day);
    }

    public function getDayOfWeekLabel(): string
    {
        $days = [
            1 => 'Monday',
            2 => 'Tuesday', 
            3 => 'Wednesday',
            4 => 'Thursday',
            5 => 'Friday',
            6 => 'Saturday',
            7 => 'Sunday'
        ];

        return $days[$this->day_of_week] ?? 'Unknown';
    }
}