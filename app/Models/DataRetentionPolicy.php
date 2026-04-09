<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DataRetentionPolicy extends Model
{
    use HasFactory;

    protected $fillable = [
        'data_type',
        'category',
        'retention_days',
        'deletion_method',
        'is_active',
        'description',
        'conditions',
        'metadata',
    ];

    protected $casts = [
        'conditions' => 'array',
        'metadata' => 'array',
        'is_active' => 'boolean',
        'retention_days' => 'integer',
    ];

    /**
     * Scope for active policies
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Check if data should be deleted based on this policy
     */
    public function shouldDelete(\DateTime $dataDate): bool
    {
        if (!$this->is_active) {
            return false;
        }

        $retentionDate = (clone $dataDate)->modify("+{$this->retention_days} days");
        return now() > $retentionDate;
    }

    /**
     * Get deletion method color for UI
     */
    public function getDeletionMethodColorAttribute(): string
    {
        return match($this->deletion_method) {
            'soft_delete' => 'yellow',
            'hard_delete' => 'red',
            'anonymize' => 'blue',
            default => 'gray'
        };
    }
}