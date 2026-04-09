<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentVerification extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'verified_by',
        'document_type',
        'document_number',
        'document_urls',
        'description',
        'status',
        'rejection_reason',
        'admin_notes',
        'submitted_at',
        'reviewed_at',
        'expires_at',
        'metadata',
        'verification_attempts',
        'last_attempt_at',
    ];

    protected $casts = [
        'document_urls' => 'array',
        'metadata' => 'array',
        'submitted_at' => 'datetime',
        'reviewed_at' => 'datetime',
        'expires_at' => 'datetime',
        'last_attempt_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // ── Relationships ─────────────────────────────────────────────────────

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    // ── Scopes ───────────────────────────────────────────────────────────

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeUnderReview($query)
    {
        return $query->where('status', 'under_review');
    }

    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    public function scopeRejected($query)
    {
        return $query->where('status', 'rejected');
    }

    public function scopeExpired($query)
    {
        return $query->where('status', 'expired')
                    ->orWhere(function ($query) {
                        $query->whereNotNull('expires_at')
                              ->where('expires_at', '<', now());
                    });
    }

    public function scopeForDocumentType($query, string $type)
    {
        return $query->where('document_type', $type);
    }

    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeRecentlySubmitted($query, int $days = 30)
    {
        return $query->where('submitted_at', '>=', now()->subDays($days));
    }

    // ── Helper Methods ───────────────────────────────────────────────────

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isUnderReview(): bool
    {
        return $this->status === 'under_review';
    }

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    public function isRejected(): bool
    {
        return $this->status === 'rejected';
    }

    public function isExpired(): bool
    {
        return $this->status === 'expired' || 
               ($this->expires_at && $this->expires_at->isPast());
    }

    public function canBeReviewed(): bool
    {
        return in_array($this->status, ['pending', 'under_review']);
    }

    public function canBeResubmitted(): bool
    {
        return in_array($this->status, ['rejected', 'expired']);
    }

    public function markAsUnderReview(User $admin): void
    {
        $this->update([
            'status' => 'under_review',
            'verified_by' => $admin->id,
            'reviewed_at' => now(),
        ]);
    }

    public function approve(User $admin, ?string $notes = null): void
    {
        $this->update([
            'status' => 'approved',
            'verified_by' => $admin->id,
            'reviewed_at' => now(),
            'admin_notes' => $notes,
        ]);
    }

    public function reject(User $admin, string $reason, ?string $notes = null): void
    {
        $this->update([
            'status' => 'rejected',
            'verified_by' => $admin->id,
            'reviewed_at' => now(),
            'rejection_reason' => $reason,
            'admin_notes' => $notes,
        ]);
    }

    public function incrementAttempts(): void
    {
        $this->increment('verification_attempts');
        $this->update(['last_attempt_at' => now()]);
    }

    // ── Static Methods ───────────────────────────────────────────────────

    public static function getDocumentTypes(): array
    {
        return [
            'driver_license' => 'Driver\'s License',
            'vehicle_registration' => 'Vehicle Registration (OR/CR)',
            'vehicle_insurance' => 'Vehicle Insurance',
            'business_permit' => 'Business Permit',
            'bir_certificate' => 'BIR Certificate',
            'valid_id' => 'Valid Government ID',
            'proof_of_address' => 'Proof of Address',
            'medical_certificate' => 'Medical Certificate',
            'police_clearance' => 'Police Clearance',
            'barangay_clearance' => 'Barangay Clearance',
            'other' => 'Other Document',
        ];
    }

    public static function getStatusLabels(): array
    {
        return [
            'pending' => 'Pending Review',
            'under_review' => 'Under Review',
            'approved' => 'Approved',
            'rejected' => 'Rejected',
            'expired' => 'Expired',
        ];
    }

    public function getStatusLabelAttribute(): string
    {
        return self::getStatusLabels()[$this->status] ?? 'Unknown';
    }

    public function getDocumentTypeNameAttribute(): string
    {
        return self::getDocumentTypes()[$this->document_type] ?? 'Unknown Document';
    }

    public function getDaysToExpiryAttribute(): ?int
    {
        if (!$this->expires_at) {
            return null;
        }

        return now()->diffInDays($this->expires_at, false);
    }

    public function getIsExpiringAttribute(): bool
    {
        $daysToExpiry = $this->days_to_expiry;
        return $daysToExpiry !== null && $daysToExpiry <= 30 && $daysToExpiry > 0;
    }
}