<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class IpBlacklist extends Model
{
    use HasFactory;

    protected $table = 'ip_blacklist';

    protected $fillable = [
        'ip_address',
        'ip_range',
        'reason',
        'description',
        'is_active',
        'expires_at',
        'created_by',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
        'is_active' => 'boolean',
        'expires_at' => 'datetime',
    ];

    /**
     * Scope for active entries
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true)
                    ->where(function($q) {
                        $q->whereNull('expires_at')
                          ->orWhere('expires_at', '>', now());
                    });
    }

    /**
     * Check if an IP address is blacklisted
     */
    public static function isBlacklisted(string $ip): bool
    {
        return static::active()
            ->where(function($query) use ($ip) {
                $query->where('ip_address', $ip)
                      ->orWhere(function($q) use ($ip) {
                          $q->whereNotNull('ip_range')
                            ->whereRaw('INET_ATON(?) & INET_ATON(SUBSTRING_INDEX(ip_range, "/", 1)) = INET_ATON(SUBSTRING_INDEX(ip_range, "/", 1))', [$ip]);
                      });
            })
            ->exists();
    }

    /**
     * Check if entry is expired
     */
    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    /**
     * Get reason color for UI
     */
    public function getReasonColorAttribute(): string
    {
        return match($this->reason) {
            'suspicious_activity' => 'yellow',
            'abuse' => 'orange',
            'security_threat' => 'red',
            default => 'gray'
        };
    }
}