<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WebSocketConnection extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'connection_id',
        'channel',
        'status',
        'connected_at',
        'last_ping_at',
        'disconnected_at',
        'metadata',
    ];

    protected $casts = [
        'connected_at' => 'datetime',
        'last_ping_at' => 'datetime',
        'disconnected_at' => 'datetime',
        'metadata' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // Relationships

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Scopes

    public function scopeConnected($query)
    {
        return $query->where('status', 'connected');
    }

    public function scopeDisconnected($query)
    {
        return $query->where('status', 'disconnected');
    }

    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeByChannel($query, string $channel)
    {
        return $query->where('channel', $channel);
    }

    public function scopeActive($query, int $minutes = 5)
    {
        return $query->where('status', 'connected')
                    ->where(function ($q) use ($minutes) {
                        $q->where('last_ping_at', '>=', now()->subMinutes($minutes))
                          ->orWhereNull('last_ping_at');
                    });
    }

    // Helper methods

    public function disconnect(): void
    {
        $this->update([
            'status' => 'disconnected',
            'disconnected_at' => now(),
        ]);
    }

    public function ping(): void
    {
        $this->update(['last_ping_at' => now()]);
    }

    public function isConnected(): bool
    {
        return $this->status === 'connected';
    }

    public function isActive(int $minutes = 5): bool
    {
        if (!$this->isConnected()) {
            return false;
        }

        if (!$this->last_ping_at) {
            return $this->connected_at >= now()->subMinutes($minutes);
        }

        return $this->last_ping_at >= now()->subMinutes($minutes);
    }

    public function getDurationConnected(): int
    {
        $endTime = $this->disconnected_at ?? now();
        return $this->connected_at->diffInSeconds($endTime);
    }
}