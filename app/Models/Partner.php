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
        'logo_url',
        'cover_image_url',
        'description',
        'category',
        'tags',
        'cuisine_types',
        'rating',
        'total_reviews',
        'delivery_fee',
        'min_order_amount',
        'estimated_delivery_minutes',
        'is_open',
        'is_featured',
        'accepts_online_payment',
        'opening_hours',
        'legal_name',
        'slug',
        'business_type',
        'tax_id',
        'support_email',
        'support_phone',
        'status',
    ];

    protected $casts = [
        'tags'                       => 'array',
        'cuisine_types'              => 'array',
        'opening_hours'              => 'array',
        'rating'                     => 'float',
        'delivery_fee'               => 'float',
        'total_reviews'              => 'integer',
        'min_order_amount'           => 'integer',
        'estimated_delivery_minutes' => 'integer',
        'is_open'                    => 'boolean',
        'is_featured'                => 'boolean',
        'accepts_online_payment'     => 'boolean',
        'created_at'                 => 'datetime',
        'updated_at'                 => 'datetime',
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

    public function menuCategories()
    {
        return $this->hasMany(MenuCategory::class, 'partner_id');
    }

    public function menuItems()
    {
        return $this->hasManyThrough(MenuItem::class, MenuCategory::class, 'partner_id', 'menu_category_id');
    }
}
