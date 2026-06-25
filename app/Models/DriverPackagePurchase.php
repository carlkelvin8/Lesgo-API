<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DriverPackagePurchase extends Model
{
    protected $fillable = [
        'user_id',
        'driver_profile_id',
        'target_tier',
        'amount',
        'currency',
        'status',
        'payment_method',
        'xendit_invoice_id',
        'external_id',
        'invoice_url',
        'paid_at',
        'meta',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'paid_at' => 'datetime',
        'meta' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function driverProfile(): BelongsTo
    {
        return $this->belongsTo(DriverProfile::class);
    }

    public function isPaid(): bool
    {
        return $this->status === 'paid';
    }
}
