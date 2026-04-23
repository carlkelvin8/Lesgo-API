<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DriverMission extends Model
{
    protected $fillable = [
        'driver_profile_id',
        'mission_template_id',
        'current_progress',
        'goal_target',
        'is_completed',
        'completed_at',
        'reward_claimed',
        'claimed_at',
        'mission_date',
    ];

    protected $casts = [
        'current_progress' => 'integer',
        'goal_target' => 'integer',
        'is_completed' => 'boolean',
        'reward_claimed' => 'boolean',
        'completed_at' => 'datetime',
        'claimed_at' => 'datetime',
        'mission_date' => 'date',
    ];

    public function driverProfile(): BelongsTo
    {
        return $this->belongsTo(DriverProfile::class);
    }

    public function missionTemplate(): BelongsTo
    {
        return $this->belongsTo(MissionTemplate::class);
    }

    public function getProgressPercentageAttribute(): float
    {
        if ($this->goal_target == 0) {
            return 0;
        }
        return min(($this->current_progress / $this->goal_target), 1.0);
    }
}
