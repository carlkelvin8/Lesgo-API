<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerSatisfactionSurvey extends Model
{
    protected $fillable = [
        'user_id',
        'rating',
        'feedback',
        'completed_orders_count',
        'source',
    ];

    protected $casts = [
        'rating'                 => 'integer',
        'completed_orders_count' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
