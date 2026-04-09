<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

class UserSession extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'token_id',
        'device_name',
        'device_type',
        'device_id',
        'platform',
        'browser',
        'ip_address',
        'user_agent',
        'location_data',
        'last_activity',
        'expires_at',
        'is_active',
        'is_trusted_device',
    ];

    protected $casts = [
        'location_data' => 'array',
        'last_activity' => 'datetime',
        'expires_at' => 'datetime',
        'is_active' => 'boolean',
        'is_trusted_device' => 'boolean',
    ];

    /**
     * Get the user that owns the session.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope to get active sessions only.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true)
                    ->where(function ($q) {
                        $q->whereNull('expires_at')
                          ->orWhere('expires_at', '>', now());
                    });
    }

    /**
     * Scope to get expired sessions.
     */
    public function scopeExpired($query)
    {
        return $query->where('expires_at', '<=', now());
    }

    /**
     * Scope to get sessions by device type.
     */
    public function scopeByDeviceType($query, string $deviceType)
    {
        return $query->where('device_type', $deviceType);
    }

    /**
     * Check if session is expired.
     */
    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    /**
     * Check if session is active.
     */
    public function isActive(): bool
    {
        return $this->is_active && !$this->isExpired();
    }

    /**
     * Mark session as inactive.
     */
    public function deactivate(): bool
    {
        return $this->update(['is_active' => false]);
    }

    /**
     * Update last activity timestamp.
     */
    public function updateActivity(): bool
    {
        return $this->update(['last_activity' => now()]);
    }

    /**
     * Get human-readable device info.
     */
    public function getDeviceInfoAttribute(): string
    {
        $parts = array_filter([
            $this->device_name,
            $this->platform,
            $this->browser,
        ]);

        return implode(' - ', $parts) ?: 'Unknown Device';
    }

    /**
     * Get session duration in human readable format.
     */
    public function getSessionDurationAttribute(): string
    {
        return $this->created_at->diffForHumans($this->last_activity, true);
    }

    /**
     * Check if this is the current session.
     */
    public function isCurrent(): bool
    {
        $currentToken = request()->user()?->currentAccessToken();
        return $currentToken && $this->token_id === $currentToken->id;
    }
}