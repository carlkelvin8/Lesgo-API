<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'customer_id',
        'partner_id',
        'driver_id',
        'service_id',
        'pickup_address_id',
        'dropoff_address_id',
        'status',
        'scheduled_at',
        'accepted_at',
        'picked_up_at',
        'completed_at',
        'cancelled_at',
        'estimated_distance_m',
        'actual_distance_m',
        'estimated_fare',
        'actual_fare',
        'partner_share',
        'driver_share',
        'platform_fee',
        'payment_method',
        'payment_status',
        'cancel_reason',
        'meta',
    ];

    protected $casts = [
        'scheduled_at'         => 'datetime',
        'accepted_at'          => 'datetime',
        'picked_up_at'         => 'datetime',
        'completed_at'         => 'datetime',
        'cancelled_at'         => 'datetime',
        'created_at'           => 'datetime',
        'updated_at'           => 'datetime',

        'estimated_distance_m' => 'integer',
        'actual_distance_m'    => 'integer',

        'estimated_fare'       => 'decimal:2',
        'actual_fare'          => 'decimal:2',
        'partner_share'        => 'decimal:2',
        'driver_share'         => 'decimal:2',
        'platform_fee'         => 'decimal:2',

        'meta'                 => 'array',
    ];

    public function customer()
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    public function partner()
    {
        return $this->belongsTo(Partner::class, 'partner_id');
    }

    public function driverProfile()
    {
        // Note: driver_id references driver_profiles.id (not users.id)
        return $this->belongsTo(DriverProfile::class, 'driver_id');
    }

    public function service()
    {
        return $this->belongsTo(Service::class, 'service_id');
    }

    public function pickupAddress()
    {
        return $this->belongsTo(Address::class, 'pickup_address_id');
    }

    public function dropoffAddress()
    {
        return $this->belongsTo(Address::class, 'dropoff_address_id');
    }

    public function payments()
    {
        return $this->hasMany(Payment::class, 'order_id');
    }

    public function lesbuyItems()
    {
        return $this->hasMany(LesbuyItem::class, 'order_id');
    }
}
