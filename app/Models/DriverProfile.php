<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DriverProfile extends Model
{
    use HasFactory;

    protected $table = 'driver_profiles';

    protected $fillable = [
        'user_id',
        'partner_id',
        'status',
        'rating',
        'total_trips',
        'license_number',
        'license_expiry_date',
        'id_document_path',
        'last_latitude',
        'last_longitude',
        'vehicle_type',
        'plate_number',
        'vehicle_plate',
    ];

    protected $casts = [
        'rating'              => 'float',
        'total_trips'         => 'integer',
        'license_expiry_date' => 'date',
        'last_latitude'       => 'float',
        'last_longitude'      => 'float',
        'created_at'          => 'datetime',
        'updated_at'          => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function partner()
    {
        return $this->belongsTo(Partner::class, 'partner_id');
    }

    public function vehicles()
    {
        return $this->hasMany(Vehicle::class, 'driver_id');
    }

    public function orders()
    {
        return $this->hasMany(Order::class, 'driver_id');
    }

    public function payments()
    {
        return $this->hasMany(Payment::class, 'driver_id');
    }
}
