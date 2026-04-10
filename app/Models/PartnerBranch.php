<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PartnerBranch extends Model
{
    use HasFactory;

    protected $table = 'partner_branches';

    protected $fillable = [
        'partner_id',
        'name',
        'logo_url',
        'phone_number',
        'address_line1',
        'address_line2',
        'city',
        'region',
        'country',
        'postal_code',
        'latitude',
        'longitude',
        'is_primary',
        'is_open',
        'estimated_delivery_minutes',
        'delivery_fee',
        'opening_hours',
    ];

    protected $casts = [
        'latitude'                   => 'float',
        'longitude'                  => 'float',
        'is_primary'                 => 'boolean',
        'is_open'                    => 'boolean',
        'delivery_fee'               => 'decimal:2',
        'estimated_delivery_minutes' => 'integer',
        'opening_hours'              => 'array',
        'created_at'                 => 'datetime',
        'updated_at'                 => 'datetime',
    ];

    public function partner()
    {
        return $this->belongsTo(Partner::class, 'partner_id');
    }
}
