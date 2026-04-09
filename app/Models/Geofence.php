<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Carbon\Carbon;

class Geofence extends Model
{
    use HasFactory;

    protected $fillable = [
        'created_by',
        'name',
        'description',
        'type',
        'shape',
        'center_latitude',
        'center_longitude',
        'radius_meters',
        'polygon_coordinates',
        'trigger_on_enter',
        'trigger_on_exit',
        'trigger_on_dwell',
        'dwell_time_seconds',
        'notification_types',
        'notification_recipients',
        'enter_message',
        'exit_message',
        'dwell_message',
        'active_days',
        'active_start_time',
        'active_end_time',
        'timezone',
        'is_active',
        'priority',
        'metadata',
        'last_triggered_at',
        'trigger_count',
    ];

    protected $casts = [
        'polygon_coordinates' => 'array',
        'notification_types' => 'array',
        'notification_recipients' => 'array',
        'active_days' => 'array',
        'metadata' => 'array',
        'trigger_on_enter' => 'boolean',
        'trigger_on_exit' => 'boolean',
        'trigger_on_dwell' => 'boolean',
        'is_active' => 'boolean',
        'last_triggered_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // ── Relationships ─────────────────────────────────────────────────────

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function events(): HasMany
    {
        return $this->hasMany(GeofenceEvent::class);
    }

    public function recentEvents(): HasMany
    {
        return $this->hasMany(GeofenceEvent::class)->orderBy('event_time', 'desc');
    }

    // ── Scopes ───────────────────────────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForType($query, string $type)
    {
        return $query->where('type', $type);
    }

    public function scopeWithinRadius($query, float $latitude, float $longitude, int $radiusKm)
    {
        return $query->selectRaw('*, (
            6371 * acos(
                cos(radians(?)) * cos(radians(center_latitude)) * 
                cos(radians(center_longitude) - radians(?)) + 
                sin(radians(?)) * sin(radians(center_latitude))
            )
        ) AS distance', [$latitude, $longitude, $latitude])
        ->having('distance', '<=', $radiusKm)
        ->orderBy('distance');
    }

    public function scopeByPriority($query, string $order = 'desc')
    {
        return $query->orderBy('priority', $order);
    }

    public function scopeTriggeredRecently($query, int $hours = 24)
    {
        return $query->where('last_triggered_at', '>=', now()->subHours($hours));
    }

    // ── Helper Methods ───────────────────────────────────────────────────

    public function isActiveNow(): bool
    {
        if (!$this->is_active) {
            return false;
        }

        $now = Carbon::now($this->timezone);
        
        // Check day of week
        if ($this->active_days && !in_array(strtolower($now->format('l')), $this->active_days)) {
            return false;
        }

        // Check time range
        if ($this->active_start_time && $this->active_end_time) {
            $startTime = Carbon::createFromTimeString($this->active_start_time, $this->timezone);
            $endTime = Carbon::createFromTimeString($this->active_end_time, $this->timezone);
            
            $currentTime = $now->format('H:i:s');
            
            if ($currentTime < $startTime->format('H:i:s') || $currentTime > $endTime->format('H:i:s')) {
                return false;
            }
        }

        return true;
    }

    public function containsPoint(float $latitude, float $longitude): bool
    {
        if ($this->shape === 'circle') {
            return $this->isPointInCircle($latitude, $longitude);
        } elseif ($this->shape === 'polygon') {
            return $this->isPointInPolygon($latitude, $longitude);
        }

        return false;
    }

    private function isPointInCircle(float $latitude, float $longitude): bool
    {
        if (!$this->radius_meters) {
            return false;
        }

        $distance = $this->calculateDistance(
            $this->center_latitude,
            $this->center_longitude,
            $latitude,
            $longitude
        );

        return $distance <= $this->radius_meters;
    }

    private function isPointInPolygon(float $latitude, float $longitude): bool
    {
        if (!$this->polygon_coordinates || count($this->polygon_coordinates) < 3) {
            return false;
        }

        $vertices = $this->polygon_coordinates;
        $intersections = 0;
        $vertexCount = count($vertices);

        for ($i = 0; $i < $vertexCount; $i++) {
            $vertex1 = $vertices[$i];
            $vertex2 = $vertices[($i + 1) % $vertexCount];

            if ($this->rayIntersectsSegment($latitude, $longitude, $vertex1, $vertex2)) {
                $intersections++;
            }
        }

        return ($intersections % 2) === 1;
    }

    private function rayIntersectsSegment(float $pointLat, float $pointLng, array $vertex1, array $vertex2): bool
    {
        $lat1 = $vertex1['latitude'];
        $lng1 = $vertex1['longitude'];
        $lat2 = $vertex2['latitude'];
        $lng2 = $vertex2['longitude'];

        if ($lat1 > $pointLat === $lat2 > $pointLat) {
            return false;
        }

        $slope = ($lng2 - $lng1) / ($lat2 - $lat1);
        $intersectLng = $lng1 + $slope * ($pointLat - $lat1);

        return $intersectLng > $pointLng;
    }

    private function calculateDistance(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthRadius = 6371000; // Earth's radius in meters

        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);

        $a = sin($dLat / 2) * sin($dLat / 2) +
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
             sin($dLng / 2) * sin($dLng / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }

    public function getDistanceFromPoint(float $latitude, float $longitude): float
    {
        return $this->calculateDistance(
            $this->center_latitude,
            $this->center_longitude,
            $latitude,
            $longitude
        );
    }

    public function incrementTriggerCount(): void
    {
        $this->increment('trigger_count');
        $this->update(['last_triggered_at' => now()]);
    }

    // ── Static Methods ───────────────────────────────────────────────────

    public static function getGeofenceTypes(): array
    {
        return [
            'delivery_zone' => 'Delivery Zone',
            'service_area' => 'Service Area',
            'restricted_area' => 'Restricted Area',
            'pickup_zone' => 'Pickup Zone',
            'partner_location' => 'Partner Location',
            'warehouse' => 'Warehouse',
            'depot' => 'Depot',
            'customer_location' => 'Customer Location',
            'custom' => 'Custom Area',
        ];
    }

    public static function getPriorityLevels(): array
    {
        return [
            1 => 'Low',
            2 => 'Medium',
            3 => 'High',
            4 => 'Critical',
        ];
    }

    public static function getNotificationTypes(): array
    {
        return [
            'push' => 'Push Notification',
            'sms' => 'SMS',
            'email' => 'Email',
            'webhook' => 'Webhook',
            'in_app' => 'In-App Notification',
        ];
    }

    public function getTypeLabel(): string
    {
        return self::getGeofenceTypes()[$this->type] ?? 'Unknown';
    }

    public function getPriorityLabel(): string
    {
        return self::getPriorityLevels()[$this->priority] ?? 'Unknown';
    }

    public function getArea(): float
    {
        if ($this->shape === 'circle' && $this->radius_meters) {
            return pi() * pow($this->radius_meters, 2);
        } elseif ($this->shape === 'polygon' && $this->polygon_coordinates) {
            return $this->calculatePolygonArea();
        }

        return 0;
    }

    private function calculatePolygonArea(): float
    {
        $vertices = $this->polygon_coordinates;
        if (count($vertices) < 3) {
            return 0;
        }

        $area = 0;
        $vertexCount = count($vertices);

        for ($i = 0; $i < $vertexCount; $i++) {
            $j = ($i + 1) % $vertexCount;
            $area += $vertices[$i]['latitude'] * $vertices[$j]['longitude'];
            $area -= $vertices[$j]['latitude'] * $vertices[$i]['longitude'];
        }

        return abs($area) / 2;
    }

    // ── Analytics Methods ─────────────────────────────────────────────────

    public function getEventStats(int $days = 30): array
    {
        $events = $this->events()
            ->where('event_time', '>=', now()->subDays($days))
            ->get();

        return [
            'total_events' => $events->count(),
            'enter_events' => $events->where('event_type', 'enter')->count(),
            'exit_events' => $events->where('event_type', 'exit')->count(),
            'dwell_events' => $events->where('event_type', 'dwell')->count(),
            'unique_users' => $events->pluck('user_id')->unique()->count(),
            'avg_dwell_time' => $events->where('event_type', 'dwell')->avg('dwell_duration_seconds'),
            'most_active_hour' => $events->groupBy(function ($event) {
                return $event->event_time->format('H');
            })->map->count()->sortDesc()->keys()->first(),
        ];
    }

    public static function findGeofencesForLocation(float $latitude, float $longitude): \Illuminate\Database\Eloquent\Collection
    {
        return self::active()
            ->get()
            ->filter(function ($geofence) use ($latitude, $longitude) {
                return $geofence->containsPoint($latitude, $longitude);
            });
    }
}