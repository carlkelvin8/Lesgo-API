<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class GeofenceAnalytics extends Model
{
    use HasFactory;

    protected $fillable = [
        'geofence_id',
        'date',
        'total_entries',
        'total_exits',
        'total_dwells',
        'unique_users',
        'average_dwell_time',
        'orders_triggered',
        'conversion_rate',
        'revenue_generated',
        'notifications_sent',
        'notification_clicks',
        'notification_ctr',
        'effectiveness_data',
    ];

    protected $casts = [
        'date' => 'date',
        'average_dwell_time' => 'decimal:2',
        'conversion_rate' => 'decimal:2',
        'revenue_generated' => 'decimal:2',
        'notification_ctr' => 'decimal:2',
        'effectiveness_data' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // Relationships

    public function geofence()
    {
        return $this->belongsTo(Geofence::class);
    }

    // Scopes

    public function scopeForGeofence($query, int $geofenceId)
    {
        return $query->where('geofence_id', $geofenceId);
    }

    public function scopeHighConversion($query, float $minRate = 10.0)
    {
        return $query->where('conversion_rate', '>=', $minRate);
    }

    public function scopeActiveGeofences($query)
    {
        return $query->where('total_entries', '>', 0);
    }

    public function scopeRevenueGenerating($query)
    {
        return $query->where('revenue_generated', '>', 0);
    }

    // Helper methods

    public function getTotalEvents(): int
    {
        return $this->total_entries + $this->total_exits + $this->total_dwells;
    }

    public function getEngagementRate(): float
    {
        if ($this->total_entries == 0) {
            return 0;
        }

        return ($this->total_dwells / $this->total_entries) * 100;
    }

    public function getRevenuePerEntry(): float
    {
        if ($this->total_entries == 0) {
            return 0;
        }

        return $this->revenue_generated / $this->total_entries;
    }

    public function getRevenuePerUser(): float
    {
        if ($this->unique_users == 0) {
            return 0;
        }

        return $this->revenue_generated / $this->unique_users;
    }

    public function getEffectivenessScore(): float
    {
        $weights = [
            'conversion_rate' => 0.4,
            'engagement_rate' => 0.2,
            'revenue_per_entry' => 0.2,
            'notification_ctr' => 0.2,
        ];

        $scores = [
            'conversion_rate' => min($this->conversion_rate / 20, 1), // Normalize to 20% max
            'engagement_rate' => min($this->getEngagementRate() / 50, 1), // Normalize to 50% max
            'revenue_per_entry' => min($this->getRevenuePerEntry() / 100, 1), // Normalize to ₱100 max
            'notification_ctr' => min($this->notification_ctr / 10, 1), // Normalize to 10% max
        ];

        $totalScore = 0;
        foreach ($weights as $metric => $weight) {
            $totalScore += ($scores[$metric] ?? 0) * $weight;
        }

        return round($totalScore * 100, 2);
    }

    public function getEffectivenessRating(): string
    {
        $score = $this->getEffectivenessScore();

        if ($score >= 80) return 'Excellent';
        if ($score >= 60) return 'Good';
        if ($score >= 40) return 'Average';
        if ($score >= 20) return 'Poor';
        return 'Very Poor';
    }

    public function getROI(): float
    {
        // Estimated cost per geofence per day
        $estimatedCost = 50; // ₱50 per day
        
        if ($estimatedCost == 0) {
            return 0;
        }

        return (($this->revenue_generated - $estimatedCost) / $estimatedCost) * 100;
    }

    public function isPerforming(): bool
    {
        return $this->conversion_rate >= 5.0 && 
               $this->revenue_generated >= 100 && 
               $this->getEffectivenessScore() >= 60;
    }

    public function getFormattedRevenue(): string
    {
        return '₱' . number_format($this->revenue_generated, 2);
    }

    public function getFormattedRevenuePerEntry(): string
    {
        return '₱' . number_format($this->getRevenuePerEntry(), 2);
    }

    public function getFormattedDwellTime(): string
    {
        if ($this->average_dwell_time < 60) {
            return number_format($this->average_dwell_time, 1) . ' min';
        }

        $hours = floor($this->average_dwell_time / 60);
        $minutes = $this->average_dwell_time % 60;
        
        return sprintf('%dh %dm', $hours, $minutes);
    }

    public function getFormattedCTR(): string
    {
        return number_format($this->notification_ctr, 2) . '%';
    }

    public function getFormattedConversionRate(): string
    {
        return number_format($this->conversion_rate, 2) . '%';
    }

    public static function calculateForGeofence(Geofence $geofence, Carbon $date): self
    {
        // Get geofence events for the date
        $events = GeofenceEvent::where('geofence_id', $geofence->id)
            ->whereDate('created_at', $date)
            ->get();

        $totalEntries = $events->where('event_type', 'enter')->count();
        $totalExits = $events->where('event_type', 'exit')->count();
        $totalDwells = $events->where('event_type', 'dwell')->count();
        $uniqueUsers = $events->unique('user_id')->count();

        // Calculate orders triggered (simplified)
        $ordersTriggered = Order::whereDate('created_at', $date)
            ->whereHas('geofenceEvents', function ($query) use ($geofence) {
                $query->where('geofence_id', $geofence->id);
            })
            ->count();

        $conversionRate = $totalEntries > 0 ? ($ordersTriggered / $totalEntries) * 100 : 0;

        // Calculate revenue generated (simplified)
        $revenueGenerated = Order::whereDate('created_at', $date)
            ->where('status', 'completed')
            ->whereHas('geofenceEvents', function ($query) use ($geofence) {
                $query->where('geofence_id', $geofence->id);
            })
            ->sum('actual_fare');

        return self::updateOrCreate(
            ['geofence_id' => $geofence->id, 'date' => $date],
            [
                'total_entries' => $totalEntries,
                'total_exits' => $totalExits,
                'total_dwells' => $totalDwells,
                'unique_users' => $uniqueUsers,
                'orders_triggered' => $ordersTriggered,
                'conversion_rate' => $conversionRate,
                'revenue_generated' => $revenueGenerated,
            ]
        );
    }
}