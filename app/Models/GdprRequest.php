<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GdprRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'request_type',
        'status',
        'description',
        'requested_data',
        'verification_token',
        'verified_at',
        'processed_at',
        'processed_by',
        'processing_notes',
        'export_file_path',
        'export_expires_at',
        'metadata',
    ];

    protected $casts = [
        'requested_data' => 'array',
        'metadata' => 'array',
        'verified_at' => 'datetime',
        'processed_at' => 'datetime',
        'export_expires_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope for pending requests
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope for processing requests
     */
    public function scopeProcessing($query)
    {
        return $query->where('status', 'processing');
    }

    /**
     * Generate verification token
     */
    public function generateVerificationToken(): string
    {
        $token = bin2hex(random_bytes(32));
        $this->update(['verification_token' => $token]);
        return $token;
    }

    /**
     * Verify the request
     */
    public function verify(): void
    {
        $this->update([
            'verified_at' => now(),
            'status' => 'processing',
        ]);
    }

    /**
     * Mark as completed
     */
    public function complete(string $processedBy, string $notes = null, string $exportPath = null): void
    {
        $this->update([
            'status' => 'completed',
            'processed_at' => now(),
            'processed_by' => $processedBy,
            'processing_notes' => $notes,
            'export_file_path' => $exportPath,
            'export_expires_at' => $exportPath ? now()->addDays(30) : null,
        ]);
    }

    /**
     * Check if export is expired
     */
    public function isExportExpired(): bool
    {
        return $this->export_expires_at && $this->export_expires_at->isPast();
    }

    /**
     * Get status color for UI
     */
    public function getStatusColorAttribute(): string
    {
        return match($this->status) {
            'pending' => 'yellow',
            'processing' => 'blue',
            'completed' => 'green',
            'rejected' => 'red',
            default => 'gray'
        };
    }
}