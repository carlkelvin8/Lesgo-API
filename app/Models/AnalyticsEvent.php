<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class AnalyticsEvent extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'event_type',
        'event_category',
        'event_action',
        'event_label',
        'event_value',
        'properties',
        'session_id',
        'device_type',
        'platform',
        'app_version',
        'ip_address',
        'user_agent',
        'latitude',
        'longitude',
        'event_time',
    ];

    protected $casts = [
        'properties' => 'array',
        'event_value' => 'decimal:2',
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
        'event_time' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // Relationships

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Scopes

    public function scopeByCategory($query, string $category)
    {
        return $query->where('event_category', $category);
    }

    public function scopeByType($query, string $type)
    {
        return $query->where('event_type', $type);
    }

    public function scopeByAction($query, string $action)
    {
        return $query->where('event_action', $action);
    }

    public function scopeInDateRange($query, Carbon $startDate, Carbon $endDate)
    {
        return $query->whereBetween('event_time', [$startDate, $endDate]);
    }

    public function scopeToday($query)
    {
        return $query->whereDate('event_time', today());
    }

    public function scopeThisWeek($query)
    {
        return $query->whereBetween('event_time', [
            now()->startOfWeek(),
            now()->endOfWeek()
        ]);
    }

    public function scopeThisMonth($query)
    {
        return $query->whereMonth('event_time', now()->month)
                    ->whereYear('event_time', now()->year);
    }

    public function scopeWithValue($query)
    {
        return $query->whereNotNull('event_value');
    }

    // Helper methods

    public static function track(
        string $eventType,
        string $category,
        string $action,
        ?User $user = null,
        ?string $label = null,
        ?float $value = null,
        array $properties = [],
        array $context = []
    ): self {
        return self::create([
            'user_id' => $user?->id,
            'event_type' => $eventType,
            'event_category' => $category,
            'event_action' => $action,
            'event_label' => $label,
            'event_value' => $value,
            'properties' => array_merge($properties, $context),
            'session_id' => $context['session_id'] ?? null,
            'device_type' => $context['device_type'] ?? null,
            'platform' => $context['platform'] ?? null,
            'app_version' => $context['app_version'] ?? null,
            'ip_address' => $context['ip_address'] ?? request()->ip(),
            'user_agent' => $context['user_agent'] ?? request()->userAgent(),
            'latitude' => $context['latitude'] ?? null,
            'longitude' => $context['longitude'] ?? null,
            'event_time' => now(),
        ]);
    }

    public function hasMonetaryValue(): bool
    {
        return $this->event_value !== null && $this->event_value > 0;
    }

    public function getFormattedValue(): string
    {
        if (!$this->hasMonetaryValue()) {
            return 'N/A';
        }

        return '₱' . number_format($this->event_value, 2);
    }
}