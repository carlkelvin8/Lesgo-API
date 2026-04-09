<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DriverLocation extends Model
{
    use HasFactory;

    protected $fillable = [
        'driver_id',
        'order_id',
        'latitude',
        'longitude',
        'accuracy',
        'speed',
        'heading',
        'altitude',
        'status',
        'recorded_at',
        'metadata',
    ];

    protected $casts = [
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
        'accuracy' => 'decimal:2',
        'speed' => 'decimal:2',
        'heading' => 'decimal:2',
        'altitude' => 'decimal:2',
        'recorded_at' => 'datetime',
        'metadata' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // Relationships

    public function driver()
    {
        return $this->belongsTo(User::class, 'driver_id');
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    // Scopes

    public function scopeOnline($query)
    {
        return $query->where('status', 'online');
    }

    public function scopeRecent($query, int $minutes = 5)
    {
        return $query->where('recorded_at', '>=', now()->subMinutes($minutes));
    }

    public function scopeForDriver($query, int $driverId)
    {
        return $query->where('driver_id', $driverId);
    }

    public function scopeForOrder($query, int $orderId)
    {
        return $query->where('order_id', $orderId);
    }

    public function scopeNearby($query, float $latitude, float $longitude, float $radiusKm = 5)
    {
        $radiusDegrees = $radiusKm / 111; // Approximate conversion
        
        return $query->whereBetween('latitude', [$latitude - $radiusDegrees, $latitude + $radiusDegrees])
                    ->whereBetween('longitude', [$longitude - $radiusDegrees, $longitude + $radiusDegrees]);
    }

    // Helper methods

    public function isRecent(int $minutes = 5): bool
    {
        return $this->recorded_at >= now()->subMinutes($minutes);
    }

    public function distanceTo(float $latitude, float $longitude): float
    {
        $earthRadius = 6371; // Earth's radius in kilometers
        
        $dLat = deg2rad($latitude - $this->latitude);
        $dLng = deg2rad($longitude - $this->longitude);
        
        $a = sin($dLat/2) * sin($dLat/2) +
             cos(deg2rad($this->latitude)) * cos(deg2rad($latitude)) *
             sin($dLng/2) * sin($dLng/2);
        
        $c = 2 * atan2(sqrt($a), sqrt(1-$a));
        
        return $earthRadius * $c;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'driver_id' => $this->driver_id,
            'order_id' => $this->order_id,
            'latitude' => (float) $this->latitude,
            'longitude' => (float) $this->longitude,
            'accuracy' => (float) $this->accuracy,
            'speed' => (float) $this->speed,
            'heading' => (float) $this->heading,
            'altitude' => (float) $this->altitude,
            'status' => $this->status,
            'recorded_at' => $this->recorded_at->toISOString(),
            'metadata' => $this->metadata,
        ];
    }
}