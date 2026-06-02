<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WalletLinkedAccount extends Model
{
    protected $fillable = [
        'user_id',
        'provider',
        'account_label',
        'account_last4',
        'is_verified',
        'meta',
    ];

    protected $casts = [
        'is_verified' => 'boolean',
        'meta'        => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
