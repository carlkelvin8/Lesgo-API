<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class DailyMetric extends Model
{
    use HasFactory;

    protected $fillable = [
        'date',
        'metric_type',
        'metric_category',
        'metric_key',
        'metric_value',
        'metadata',
    ];

    protected $casts = [
        'date' => 'date',
        'metric_value' => 'decimal:2',
        'metadata' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // Scopes

    public function scopeByType($query, string $type)
    {
        return $query->where('metric_type', $type);
    }

    public function scopeByCategory($query, ?string $category)
    {
        if ($category === null) {
            return $query->whereNull('metric_category');
        }
        return $query->where('metric_category', $category);
    }

    public function scopeByKey($query, string $key)
    {
        return $query->where('metric_key', $key);
    }

    public function scopeInDateRange($query, Carbon $startDate, Carbon $endDate)
    {
        return $query->whereBetween('date', [$startDate, $endDate]);
    }

    public function scopeForDate($query, Carbon $date)
    {
        return $query->whereDate('date', $date);
    }

    public function scopeThisWeek($query)
    {
        return $query->whereBetween('date', [
            now()->startOfWeek()->toDateString(),
            now()->endOfWeek()->toDateString()
        ]);
    }

    public function scopeThisMonth($query)
    {
        return $query->whereMonth('date', now()->month)
                    ->whereYear('date', now()->year);
    }

    public function scopeLastNDays($query, int $days)
    {
        return $query->whereBetween('date', [
            now()->subDays($days)->toDateString(),
            now()->toDateString()
        ]);
    }

    // Helper methods

    public static function record(
        Carbon $date,
        string $type,
        string $key,
        float $value,
        ?string $category = null,
        array $metadata = []
    ): self {
        return self::updateOrCreate(
            [
                'date' => $date->toDateString(),
                'metric_type' => $type,
                'metric_category' => $category,
                'metric_key' => $key,
            ],
            [
                'metric_value' => $value,
                'metadata' => $metadata,
            ]
        );
    }

    public static function increment(
        Carbon $date,
        string $type,
        string $key,
        float $increment = 1,
        ?string $category = null,
        array $metadata = []
    ): self {
        $metric = self::firstOrCreate(
            [
                'date' => $date->toDateString(),
                'metric_type' => $type,
                'metric_category' => $category,
                'metric_key' => $key,
            ],
            [
                'metric_value' => 0,
                'metadata' => $metadata,
            ]
        );

        $metric->increment('metric_value', $increment);
        
        if (!empty($metadata)) {
            $metric->update(['metadata' => array_merge($metric->metadata ?? [], $metadata)]);
        }

        return $metric;
    }

    public function getFormattedValue(): string
    {
        if (str_contains($this->metric_key, 'revenue') || str_contains($this->metric_key, 'earnings')) {
            return '₱' . number_format($this->metric_value, 2);
        }

        if (str_contains($this->metric_key, 'rate') || str_contains($this->metric_key, 'percentage')) {
            return number_format($this->metric_value, 2) . '%';
        }

        return number_format($this->metric_value, 0);
    }

    public function getGrowthRate(?Carbon $compareDate = null): ?float
    {
        $compareDate = $compareDate ?? $this->date->subDay();
        
        $previousMetric = self::where('metric_type', $this->metric_type)
            ->where('metric_category', $this->metric_category)
            ->where('metric_key', $this->metric_key)
            ->where('date', $compareDate)
            ->first();

        if (!$previousMetric || $previousMetric->metric_value == 0) {
            return null;
        }

        return (($this->metric_value - $previousMetric->metric_value) / $previousMetric->metric_value) * 100;
    }
}