<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Address extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'label',
        'contact_name',
        'contact_phone',
        'address_line1',
        'address_line2',
        'city',
        'region',
        'country',
        'postal_code',
        'latitude',
        'longitude',
        'is_default',
    ];

    protected $casts = [
        'latitude'   => 'float',
        'longitude'  => 'float',
        'is_default' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function pickupOrders()
    {
        return $this->hasMany(Order::class, 'pickup_address_id');
    }

    public function dropoffOrders()
    {
        return $this->hasMany(Order::class, 'dropoff_address_id');
    }
}
