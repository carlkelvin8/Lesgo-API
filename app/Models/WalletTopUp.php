<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WalletTopUp extends Model
{
    protected $fillable = [
        'user_id',
        'wallet_id',
        'amount',
        'fee',
        'total_charged',
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
        'amount'         => 'decimal:2',
        'fee'            => 'decimal:2',
        'total_charged'  => 'decimal:2',
        'paid_at'        => 'datetime',
        'meta'           => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }

    public function isPaid(): bool
    {
        return $this->status === 'paid';
    }
}
