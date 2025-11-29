<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Partner extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'legal_name',
        'slug',
        'business_type',
        'tax_id',
        'support_email',
        'support_phone',
        'status',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function branches()
    {
        return $this->hasMany(PartnerBranch::class, 'partner_id');
    }

    public function services()
    {
        return $this->hasMany(Service::class, 'partner_id');
    }

    public function driverProfiles()
    {
        return $this->hasMany(DriverProfile::class, 'partner_id');
    }

    public function vehicles()
    {
        return $this->hasMany(Vehicle::class, 'partner_id');
    }

    public function orders()
    {
        return $this->hasMany(Order::class, 'partner_id');
    }

    public function payments()
    {
        return $this->hasMany(Payment::class, 'partner_id');
    }
}
