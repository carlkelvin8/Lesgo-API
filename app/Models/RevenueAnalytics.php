<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class RevenueAnalytics extends Model
{
    use HasFactory;

    protected $fillable = [
        'date',
        'revenue_type',
        'revenue_source',
        'service_id',
        'partner_id',
        'amount',
        'currency',
        'transaction_count',
        'average_transaction_value',
        'breakdown',
    ];

    protected $casts = [
        'date' => 'date',
        'amount' => 'decimal:2',
        'average_transaction_value' => 'decimal:2',
        'breakdown' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // Relationships

    public function service()
    {
        return $this->belongsTo(Service::class);
    }

    public function partner()
    {
        return $this->belongsTo(Partner::class);
    }

    // Scopes

    public function scopeByType($query, string $type)
    {
        return $query->where('revenue_type', $type);
    }

    public function scopeBySource($query, string $source)
    {
        return $query->where('revenue_source', $source);
    }

    public function scopeForService($query, int $serviceId)
    {
        return $query->where('service_id', $serviceId);
    }

    public function scopeForPartner($query, int $partnerId)
    {
        return $query->where('partner_id', $partnerId);
    }

    public function scopeInDateRange($query, Carbon $startDate, Carbon $endDate)
    {
        return $query->whereBetween('date', [$startDate, $endDate]);
    }

    public function scopeThisMonth($query)
    {
        return $query->whereMonth('date', now()->month)
                    ->whereYear('date', now()->year);
    }

    public function scopeLastMonth($query)
    {
        $lastMonth = now()->subMonth();
        return $query->whereMonth('date', $lastMonth->month)
                    ->whereYear('date', $lastMonth->year);
    }

    // Helper methods

    public function getFormattedAmount(): string
    {
        return $this->currency . ' ' . number_format($this->amount, 2);
    }

    public function getFormattedATV(): string
    {
        return $this->currency . ' ' . number_format($this->average_transaction_value, 2);
    }

    public function getRevenueTypeLabel(): string
    {
        return match ($this->revenue_type) {
            'gross' => 'Gross Revenue',
            'net' => 'Net Revenue',
            'commission' => 'Platform Commission',
            'driver_earnings' => 'Driver Earnings',
            'partner_earnings' => 'Partner Earnings',
            'platform_fee' => 'Platform Fee',
            default => ucfirst(str_replace('_', ' ', $this->revenue_type)),
        };
    }

    public function getRevenueSourceLabel(): string
    {
        return match ($this->revenue_source) {
            'orders' => 'Order Revenue',
            'subscriptions' => 'Subscription Revenue',
            'fees' => 'Service Fees',
            'penalties' => 'Penalty Fees',
            default => ucfirst(str_replace('_', ' ', $this->revenue_source)),
        };
    }

    public function calculateGrowthRate(?Carbon $compareDate = null): ?float
    {
        $compareDate = $compareDate ?? $this->date->subDay();
        
        $previousRevenue = self::where('revenue_type', $this->revenue_type)
            ->where('revenue_source', $this->revenue_source)
            ->where('service_id', $this->service_id)
            ->where('partner_id', $this->partner_id)
            ->where('date', $compareDate)
            ->first();

        if (!$previousRevenue || $previousRevenue->amount == 0) {
            return null;
        }

        return (($this->amount - $previousRevenue->amount) / $previousRevenue->amount) * 100;
    }

    public function getMarginPercentage(): ?float
    {
        if ($this->revenue_type !== 'commission' && $this->revenue_type !== 'platform_fee') {
            return null;
        }

        $grossRevenue = self::where('date', $this->date)
            ->where('revenue_type', 'gross')
            ->where('service_id', $this->service_id)
            ->where('partner_id', $this->partner_id)
            ->first();

        if (!$grossRevenue || $grossRevenue->amount == 0) {
            return null;
        }

        return ($this->amount / $grossRevenue->amount) * 100;
    }

    public static function recordRevenue(
        Carbon $date,
        string $type,
        string $source,
        float $amount,
        int $transactionCount = 1,
        ?int $serviceId = null,
        ?int $partnerId = null,
        array $breakdown = []
    ): self {
        $averageTransactionValue = $transactionCount > 0 ? $amount / $transactionCount : 0;

        return self::updateOrCreate(
            [
                'date' => $date,
                'revenue_type' => $type,
                'revenue_source' => $source,
                'service_id' => $serviceId,
                'partner_id' => $partnerId,
            ],
            [
                'amount' => $amount,
                'transaction_count' => $transactionCount,
                'average_transaction_value' => $averageTransactionValue,
                'breakdown' => $breakdown,
            ]
        );
    }

    public static function getRevenueTypes(): array
    {
        return [
            'gross' => 'Gross Revenue',
            'net' => 'Net Revenue', 
            'commission' => 'Platform Commission',
            'driver_earnings' => 'Driver Earnings',
            'partner_earnings' => 'Partner Earnings',
            'platform_fee' => 'Platform Fee',
        ];
    }

    public static function getRevenueSources(): array
    {
        return [
            'orders' => 'Order Revenue',
            'subscriptions' => 'Subscription Revenue',
            'fees' => 'Service Fees',
            'penalties' => 'Penalty Fees',
        ];
    }
}