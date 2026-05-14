<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerMission extends Model
{
    protected $fillable = [
        'user_id',
        'mission_type',
        'title',
        'description',
        'current_progress',
        'goal_target',
        'is_completed',
        'completed_at',
        'reward_type',
        'reward_value',
        'reward_claimed',
        'claimed_at',
        'mission_date',
    ];

    protected $casts = [
        'is_completed' => 'boolean',
        'reward_claimed' => 'boolean',
        'completed_at' => 'datetime',
        'claimed_at' => 'datetime',
        'mission_date' => 'date',
        'current_progress' => 'integer',
        'goal_target' => 'integer',
        'reward_value' => 'decimal:2',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getProgressPercentageAttribute(): float
    {
        if ($this->goal_target == 0) {
            return 0;
        }
        return min(($this->current_progress / $this->goal_target) * 100, 100);
    }
}