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
        'opening_hours',
    ];

    protected $casts = [
        'latitude'      => 'float',
        'longitude'     => 'float',
        'is_primary'    => 'boolean',
        'opening_hours' => 'array',
        'created_at'    => 'datetime',
        'updated_at'    => 'datetime',
    ];

    public function partner()
    {
        return $this->belongsTo(Partner::class, 'partner_id');
    }
}
