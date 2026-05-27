<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WalletTransaction extends Model
{
    use HasFactory;

    protected $table = 'wallet_transactions';

    protected $fillable = [
        'wallet_id',
        'type',
        'source_type',
        'source_id',
        'amount',
        'balance_before',
        'balance_after',
        'description',
        'reference',
        'created_by',
    ];

    protected $casts = [
        'amount'          => 'decimal:2',
        'balance_before'  => 'decimal:2',
        'balance_after'   => 'decimal:2',
        'created_at'      => 'datetime',
        'updated_at'      => 'datetime',
    ];

    public function wallet()
    {
        return $this->belongsTo(Wallet::class, 'wallet_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
