<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GeofenceEvent extends Model
{
    use HasFactory;

    protected $fillable = [
        'geofence_id',
        'user_id',
        'order_id',
        'event_type',
        'latitude',
        'longitude',
        'address',
        'accuracy_meters',
        'speed_kmh',
        'bearing_degrees',
        'event_time',
        'dwell_start_time',
        'dwell_duration_seconds',
        'device_id',
        'device_type',
        'device_info',
        'notification_sent',
        'notification_sent_at',
        'notification_results',
        'webhook_sent',
        'webhook_sent_at',
        'metadata',
        'session_id',
    ];

    protected $casts = [
        'device_info' => 'array',
        'notification_results' => 'array',
        'metadata' => 'array',
        'notification_sent' => 'boolean',
        'webhook_sent' => 'boolean',
        'event_time' => 'datetime',
        'dwell_start_time' => 'datetime',
        'notification_sent_at' => 'datetime',
        'webhook_sent_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // ── Relationships ─────────────────────────────────────────────────────

    public function geofence(): BelongsTo
    {
        return $this->belongsTo(Geofence::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    // ── Scopes ───────────────────────────────────────────────────────────

    public function scopeForEventType($query, string $eventType)
    {
        return $query->where('event_type', $eventType);
    }

    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeForGeofence($query, int $geofenceId)
    {
        return $query->where('geofence_id', $geofenceId);
    }

    public function scopeRecent($query, int $hours = 24)
    {
        return $query->where('event_time', '>=', now()->subHours($hours));
    }

    public function scopePendingNotification($query)
    {
        return $query->where('notification_sent', false);
    }

    public function scopePendingWebhook($query)
    {
        return $query->where('webhook_sent', false);
    }

    public function scopeBySession($query, string $sessionId)
    {
        return $query->where('session_id', $sessionId);
    }

    public function scopeWithLocation($query)
    {
        return $query->whereNotNull('latitude')->whereNotNull('longitude');
    }

    // ── Helper Methods ───────────────────────────────────────────────────

    public function isEnterEvent(): bool
    {
        return $this->event_type === 'enter';
    }

    public function isExitEvent(): bool
    {
        return $this->event_type === 'exit';
    }

    public function isDwellEvent(): bool
    {
        return $this->event_type === 'dwell';
    }

    public function hasAccurateLocation(): bool
    {
        return $this->accuracy_meters && $this->accuracy_meters <= 100; // Within 100 meters
    }

    public function getFormattedAddress(): string
    {
        return $this->address ?? "Lat: {$this->latitude}, Lng: {$this->longitude}";
    }

    public function getEventTypeLabel(): string
    {
        return match ($this->event_type) {
            'enter' => 'Entered',
            'exit' => 'Exited',
            'dwell' => 'Dwelling',
            default => ucfirst($this->event_type),
        };
    }

    public function getEventIcon(): string
    {
        return match ($this->event_type) {
            'enter' => '🟢',
            'exit' => '🔴',
            'dwell' => '🟡',
            default => '📍',
        };
    }

    public function getEventColor(): string
    {
        return match ($this->event_type) {
            'enter' => '#10B981', // Green
            'exit' => '#EF4444',  // Red
            'dwell' => '#F59E0B', // Yellow
            default => '#6B7280', // Gray
        };
    }

    public function getDwellDurationFormatted(): ?string
    {
        if (!$this->dwell_duration_seconds) {
            return null;
        }

        $minutes = floor($this->dwell_duration_seconds / 60);
        $seconds = $this->dwell_duration_seconds % 60;

        if ($minutes > 0) {
            return "{$minutes}m {$seconds}s";
        }

        return "{$seconds}s";
    }

    public function getSpeedFormatted(): ?string
    {
        if (!$this->speed_kmh) {
            return null;
        }

        return number_format($this->speed_kmh, 1) . ' km/h';
    }

    public function getBearingFormatted(): ?string
    {
        if (!$this->bearing_degrees) {
            return null;
        }

        $directions = ['N', 'NNE', 'NE', 'ENE', 'E', 'ESE', 'SE', 'SSE', 'S', 'SSW', 'SW', 'WSW', 'W', 'WNW', 'NW', 'NNW'];
        $index = round($this->bearing_degrees / 22.5) % 16;
        
        return $directions[$index] . ' (' . number_format($this->bearing_degrees, 1) . '°)';
    }

    public function markNotificationSent(array $results = []): void
    {
        $this->update([
            'notification_sent' => true,
            'notification_sent_at' => now(),
            'notification_results' => $results,
        ]);
    }

    public function markWebhookSent(): void
    {
        $this->update([
            'webhook_sent' => true,
            'webhook_sent_at' => now(),
        ]);
    }

    // ── Static Methods ───────────────────────────────────────────────────

    public static function getEventTypes(): array
    {
        return [
            'enter' => 'Enter',
            'exit' => 'Exit',
            'dwell' => 'Dwell',
        ];
    }

    public static function createFromLocation(
        Geofence $geofence,
        User $user,
        string $eventType,
        float $latitude,
        float $longitude,
        array $additionalData = []
    ): self {
        return self::create(array_merge([
            'geofence_id' => $geofence->id,
            'user_id' => $user->id,
            'event_type' => $eventType,
            'latitude' => $latitude,
            'longitude' => $longitude,
            'event_time' => now(),
        ], $additionalData));
    }

    // ── Analytics Methods ─────────────────────────────────────────────────

    public static function getEventStatistics(int $days = 30): array
    {
        $events = self::where('event_time', '>=', now()->subDays($days))->get();

        return [
            'total_events' => $events->count(),
            'enter_events' => $events->where('event_type', 'enter')->count(),
            'exit_events' => $events->where('event_type', 'exit')->count(),
            'dwell_events' => $events->where('event_type', 'dwell')->count(),
            'unique_users' => $events->pluck('user_id')->unique()->count(),
            'unique_geofences' => $events->pluck('geofence_id')->unique()->count(),
            'avg_accuracy' => $events->whereNotNull('accuracy_meters')->avg('accuracy_meters'),
            'avg_speed' => $events->whereNotNull('speed_kmh')->avg('speed_kmh'),
            'notification_success_rate' => $events->where('notification_sent', true)->count() / max($events->count(), 1) * 100,
        ];
    }

    public static function getUserActivitySummary(int $userId, int $days = 30): array
    {
        $events = self::where('user_id', $userId)
            ->where('event_time', '>=', now()->subDays($days))
            ->with('geofence')
            ->get();

        return [
            'total_events' => $events->count(),
            'geofences_visited' => $events->pluck('geofence_id')->unique()->count(),
            'most_visited_geofence' => $events->groupBy('geofence_id')->map->count()->sortDesc()->keys()->first(),
            'total_dwell_time' => $events->where('event_type', 'dwell')->sum('dwell_duration_seconds'),
            'avg_dwell_time' => $events->where('event_type', 'dwell')->avg('dwell_duration_seconds'),
            'activity_by_hour' => $events->groupBy(function ($event) {
                return $event->event_time->format('H');
            })->map->count(),
            'activity_by_day' => $events->groupBy(function ($event) {
                return $event->event_time->format('Y-m-d');
            })->map->count(),
        ];
    }
}