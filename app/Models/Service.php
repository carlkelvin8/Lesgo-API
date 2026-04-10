<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Service extends Model
{
    use HasFactory;

    protected $fillable = [
        'partner_id',
        'code',
        'name',
        'description',
        'icon_url',
        'image_url',
        'category',
        'features',
        'sort_order',
        'base_fare',
        'per_km_rate',
        'per_minute_rate',
        'minimum_fare',
        'is_active',
    ];

    protected $casts = [
        'base_fare'       => 'decimal:2',
        'per_km_rate'     => 'decimal:2',
        'per_minute_rate' => 'decimal:2',
        'minimum_fare'    => 'decimal:2',
        'features'        => 'array',
        'sort_order'      => 'integer',
        'is_active'       => 'boolean',
        'created_at'      => 'datetime',
        'updated_at'      => 'datetime',
    ];

    public function partner()
    {
        return $this->belongsTo(Partner::class, 'partner_id');
    }

    public function orders()
    {
        return $this->hasMany(Order::class, 'service_id');
    }
}
