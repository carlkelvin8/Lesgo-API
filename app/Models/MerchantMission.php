<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MerchantMission extends Model
{
    protected $fillable = [
        'partner_id',
        'mission_template_id',
        'mission_date',
        'current_progress',
        'goal_target',
        'is_completed',
        'reward_claimed',
        'completed_at',
        'claimed_at',
    ];

    protected $casts = [
        'mission_date' => 'date',
        'current_progress' => 'integer',
        'goal_target' => 'integer',
        'is_completed' => 'boolean',
        'reward_claimed' => 'boolean',
        'completed_at' => 'datetime',
        'claimed_at' => 'datetime',
    ];

    public function partner()
    {
        return $this->belongsTo(Partner::class);
    }

    public function missionTemplate()
    {
        return $this->belongsTo(MissionTemplate::class);
    }

    public function getProgressPercentageAttribute(): float
    {
        if ($this->goal_target <= 0) return 0;
        return min(1.0, $this->current_progress / $this->goal_target);
    }
}
