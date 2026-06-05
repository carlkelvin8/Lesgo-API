<?php

namespace App\Models;

use App\Services\MediaStorageService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DriverDutyAttendance extends Model
{
    protected $fillable = [
        'driver_profile_id',
        'user_id',
        'photo_path',
        'on_duty',
        'latitude',
        'longitude',
        'captured_at',
    ];

    protected $casts = [
        'on_duty'     => 'boolean',
        'latitude'    => 'float',
        'longitude'   => 'float',
        'captured_at' => 'datetime',
    ];

    protected $appends = ['photo_url'];

    public function driverProfile(): BelongsTo
    {
        return $this->belongsTo(DriverProfile::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getPhotoUrlAttribute(): ?string
    {
        return MediaStorageService::publicUrl($this->photo_path);
    }
}
