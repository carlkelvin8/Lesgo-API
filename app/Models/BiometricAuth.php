<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BiometricAuth extends Model
{
    use HasFactory;

    protected $table = 'biometric_auth';

    protected $fillable = [
        'user_id',
        'biometric_type',
        'device_id',
        'biometric_hash',
        'public_key',
        'is_active',
        'enrolled_at',
        'last_used_at',
        'usage_count',
        'device_info',
        'metadata',
    ];

    protected $casts = [
        'device_info' => 'array',
        'metadata' => 'array',
        'is_active' => 'boolean',
        'enrolled_at' => 'datetime',
        'last_used_at' => 'datetime',
        'usage_count' => 'integer',
    ];

    protected $hidden = [
        'biometric_hash',
        'public_key',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Record biometric usage
     */
    public function recordUsage(): void
    {
        $this->increment('usage_count');
        $this->update(['last_used_at' => now()]);
    }

    /**
     * Check if biometric is expired (not used for 90 days)
     */
    public function isExpired(): bool
    {
        if (!$this->last_used_at) {
            return false;
        }
        
        return $this->last_used_at->diffInDays(now()) > 90;
    }

    /**
     * Deactivate biometric authentication
     */
    public function deactivate(): void
    {
        $this->update(['is_active' => false]);
    }
}