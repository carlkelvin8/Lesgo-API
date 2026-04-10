<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class CustomerBehaviorMetric extends Model
{
    use HasFactory;

    protected $fillable = [
        'customer_id',
        'date',
        'total_orders',
        'completed_orders',
        'cancelled_orders',
        'total_spent',
        'average_order_value',
        'app_sessions',
        'session_duration_minutes',
        'preferred_services',
        'preferred_times',
        'preferred_locations',
        'customer_lifetime_value',
        'referrals_made',
        'churn_probability',
        'behavior_data',
    ];

    protected $casts = [
        'date' => 'date',
        'total_spent' => 'decimal:2',
        'average_order_value' => 'decimal:2',
        'customer_lifetime_value' => 'decimal:2',
        'churn_probability' => 'decimal:4',
        'preferred_services' => 'array',
        'preferred_times' => 'array',
        'preferred_locations' => 'array',
        'behavior_data' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // Relationships

    public function customer()
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    // Scopes

    public function scopeForCustomer($query, int $customerId)
    {
        return $query->where('customer_id', $customerId);
    }

    public function scopeInDateRange($query, Carbon $startDate, Carbon $endDate)
    {
        return $query->whereBetween('date', [$startDate, $endDate]);
    }

    public function scopeHighValue($query, float $minValue = 1000)
    {
        return $query->where('customer_lifetime_value', '>=', $minValue);
    }

    public function scopeAtRiskOfChurn($query, float $threshold = 0.7)
    {
        return $query->where('churn_probability', '>=', $threshold);
    }

    public function scopeActiveCustomers($query)
    {
        return $query->where('total_orders', '>', 0);
    }

    public function scopeFrequentUsers($query, int $minOrders = 5)
    {
        return $query->where('total_orders', '>=', $minOrders);
    }

    // Helper methods

    public function getCustomerSegment(): string
    {
        $clv = $this->customer_lifetime_value;
        $frequency = $this->total_orders;
        $recency = $this->date->diffInDays(now());

        if ($clv >= 5000 && $frequency >= 10 && $recency <= 30) {
            return 'VIP';
        } elseif ($clv >= 2000 && $frequency >= 5 && $recency <= 60) {
            return 'High Value';
        } elseif ($frequency >= 3 && $recency <= 90) {
            return 'Regular';
        } elseif ($recency <= 180) {
            return 'Occasional';
        } else {
            return 'Inactive';
        }
    }

    public function getEngagementLevel(): string
    {
        $sessions = $this->app_sessions;
        $avgSessionDuration = $sessions > 0 ? $this->session_duration_minutes / $sessions : 0;

        if ($sessions >= 20 && $avgSessionDuration >= 10) {
            return 'Highly Engaged';
        } elseif ($sessions >= 10 && $avgSessionDuration >= 5) {
            return 'Engaged';
        } elseif ($sessions >= 5) {
            return 'Moderately Engaged';
        } else {
            return 'Low Engagement';
        }
    }

    public function getChurnRisk(): string
    {
        if ($this->churn_probability >= 0.8) {
            return 'Very High';
        } elseif ($this->churn_probability >= 0.6) {
            return 'High';
        } elseif ($this->churn_probability >= 0.4) {
            return 'Medium';
        } elseif ($this->churn_probability >= 0.2) {
            return 'Low';
        } else {
            return 'Very Low';
        }
    }

    public function calculateRetentionScore(): float
    {
        $factors = [
            'order_frequency' => min($this->total_orders / 10, 1) * 0.3,
            'spending_level' => min($this->total_spent / 5000, 1) * 0.25,
            'engagement' => min($this->app_sessions / 20, 1) * 0.2,
            'completion_rate' => $this->total_orders > 0 ? ($this->completed_orders / $this->total_orders) * 0.15 : 0,
            'recency' => max(0, 1 - ($this->date->diffInDays(now()) / 365)) * 0.1,
        ];

        return round(array_sum($factors) * 100, 2);
    }

    public function getPredictedNextOrderDate(): ?Carbon
    {
        if ($this->total_orders < 2) {
            return null;
        }

        // Simple prediction based on average order frequency
        $avgDaysBetweenOrders = $this->behavior_data['avg_days_between_orders'] ?? 30;
        
        return $this->date->addDays($avgDaysBetweenOrders);
    }

    public function getPreferredServiceNames(): array
    {
        if (empty($this->preferred_services)) {
            return [];
        }

        return Service::whereIn('id', $this->preferred_services)
            ->pluck('name')
            ->toArray();
    }

    public function getFormattedSpending(): string
    {
        return '₱' . number_format($this->total_spent, 2);
    }

    public function getFormattedCLV(): string
    {
        return '₱' . number_format($this->customer_lifetime_value, 2);
    }

    public function getFormattedAOV(): string
    {
        return '₱' . number_format($this->average_order_value, 2);
    }

    public static function calculateForCustomer(User $customer, Carbon $date): self
    {
        $orders = Order::where('customer_id', $customer->id)
            ->whereDate('created_at', '<=', $date)
            ->get();

        $totalOrders = $orders->count();
        $completedOrders = $orders->where('status', 'completed')->count();
        $cancelledOrders = $orders->where('status', 'cancelled')->count();
        $totalSpent = $orders->where('status', 'completed')->sum('actual_fare');
        $averageOrderValue = $completedOrders > 0 ? $totalSpent / $completedOrders : 0;

        // Calculate preferred services
        $serviceFrequency = $orders->groupBy('service_id')
            ->map->count()
            ->sortDesc()
            ->take(3)
            ->keys()
            ->toArray();

        // Calculate CLV (simplified)
        $customerLifetimeValue = $totalSpent * 1.5; // Simple multiplier

        // Calculate churn probability (simplified)
        $daysSinceLastOrder = $orders->max('created_at') ? 
            Carbon::parse($orders->max('created_at'))->diffInDays(now()) : 365;
        $churnProbability = min($daysSinceLastOrder / 180, 1); // Higher if no recent orders

        return self::updateOrCreate(
            ['customer_id' => $customer->id, 'date' => $date],
            [
                'total_orders' => $totalOrders,
                'completed_orders' => $completedOrders,
                'cancelled_orders' => $cancelledOrders,
                'total_spent' => $totalSpent,
                'average_order_value' => $averageOrderValue,
                'preferred_services' => $serviceFrequency,
                'customer_lifetime_value' => $customerLifetimeValue,
                'churn_probability' => $churnProbability,
            ]
        );
    }
}