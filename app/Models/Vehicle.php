<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Vehicle extends Model
{
    use HasFactory;

    protected $fillable = [
        'driver_id',
        'partner_id',
        'type',
        'plate_number',
        'brand',
        'model',
        'color',
        'year',
        'is_primary',
        'status',
    ];

    protected $casts = [
        'year'       => 'integer',
        'is_primary' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function driverProfile()
    {
        return $this->belongsTo(DriverProfile::class, 'driver_id');
    }

    public function partner()
    {
        return $this->belongsTo(Partner::class, 'partner_id');
    }
}
