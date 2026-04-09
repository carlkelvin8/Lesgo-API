<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentSecurityLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'transaction_id',
        'event_type',
        'pci_event_category',
        'masked_card_number',
        'payment_method',
        'processor',
        'ip_address',
        'user_agent',
        'is_compliant',
        'compliance_notes',
        'security_context',
        'metadata',
        'processed_at',
    ];

    protected $casts = [
        'security_context' => 'array',
        'metadata' => 'array',
        'is_compliant' => 'boolean',
        'processed_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope for non-compliant events
     */
    public function scopeNonCompliant($query)
    {
        return $query->where('is_compliant', false);
    }

    /**
     * Scope for recent events
     */
    public function scopeRecent($query, int $hours = 24)
    {
        return $query->where('processed_at', '>=', now()->subHours($hours));
    }

    /**
     * Scope by processor
     */
    public function scopeByProcessor($query, string $processor)
    {
        return $query->where('processor', $processor);
    }

    /**
     * Get compliance color for UI
     */
    public function getComplianceColorAttribute(): string
    {
        return $this->is_compliant ? 'green' : 'red';
    }
}