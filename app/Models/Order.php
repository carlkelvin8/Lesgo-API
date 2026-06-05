<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    use HasFactory;

    protected $appends = [
        'proof_image_urls',
    ];

    protected $fillable = [
        'customer_id',
        'partner_id',
        'driver_id',
        'service_id',
        'pickup_address_id',
        'dropoff_address_id',
        // Inline address fields
        'pickup_address',
        'pickup_lat',
        'pickup_lng',
        'pickup_contact_name',
        'pickup_contact_phone',
        'dropoff_address',
        'dropoff_lat',
        'dropoff_lng',
        'dropoff_contact_name',
        'dropoff_contact_phone',
        'notes',
        'proof_images',
        'proof_uploaded_at',
        'item_description',
        'estimated_weight_kg',
        // Status & timing
        'status',
        'scheduled_at',
        'accepted_at',
        'driver_arrived_at_pickup_at',
        'in_progress_at',
        'picked_up_at',
        'completed_at',
        'cancelled_at',
        // Distance & fare
        'estimated_distance_m',
        'actual_distance_m',
        'estimated_fare',
        'fare_breakdown',
        'actual_fare',
        'partner_share',
        'driver_share',
        'platform_fee',
        // Payment
        'payment_method',
        'payment_status',
        'voucher_code',
        'discount_amount',
        'vehicle_type',
        'passenger_name',
        'cancel_reason',
        'meta',
    ];

    protected $casts = [
        'scheduled_at'                  => 'datetime',
        'accepted_at'                   => 'datetime',
        'driver_arrived_at_pickup_at'   => 'datetime',
        'in_progress_at'                => 'datetime',
        'picked_up_at'                  => 'datetime',
        'completed_at'                  => 'datetime',
        'cancelled_at'                  => 'datetime',
        'proof_uploaded_at'             => 'datetime',
        'created_at'           => 'datetime',
        'updated_at'           => 'datetime',

        'estimated_distance_m' => 'integer',
        'actual_distance_m'    => 'integer',

        'pickup_lat'           => 'decimal:7',
        'pickup_lng'           => 'decimal:7',
        'dropoff_lat'          => 'decimal:7',
        'dropoff_lng'          => 'decimal:7',

        'estimated_fare'       => 'decimal:2',
        'fare_breakdown'       => 'array',
        'actual_fare'          => 'decimal:2',
        'discount_amount'      => 'decimal:2',
        'partner_share'        => 'decimal:2',
        'driver_share'         => 'decimal:2',
        'platform_fee'         => 'decimal:2',

        'meta'                 => 'array',
        'proof_images'         => 'array',
    ];

    // ── Query scopes ─────────────────────────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->whereNotIn('status', ['completed', 'cancelled']);
    }

    public function scopeForCustomer($query, int $customerId)
    {
        return $query->where('customer_id', $customerId);
    }

    public function scopeForDriver($query, int $driverProfileId)
    {
        return $query->where('driver_id', $driverProfileId);
    }

    public function scopeForPartner($query, int $partnerId)
    {
        return $query->where('partner_id', $partnerId);
    }

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

    /**
     * Get the tracking events for this order.
     */
    public function trackingEvents()
    {
        return $this->hasMany(OrderTrackingEvent::class);
    }

    /**
     * Get the reviews for this order.
     */
    public function reviews()
    {
        return $this->hasMany(RatingReview::class);
    }

    /**
     * Get the support tickets for this order.
     */
    public function supportTickets()
    {
        return $this->hasMany(SupportTicket::class);
    }

    /**
     * Get the driver user (for tracking events).
     */
    public function driver()
    {
        return $this->belongsTo(User::class, 'driver_id');
    }

    /**
     * Get the social shares for this order.
     */
    public function socialShares()
    {
        return $this->hasMany(SocialShare::class);
    }

    /**
     * Get the geofence events for this order.
     */
    public function geofenceEvents()
    {
        return $this->hasMany(GeofenceEvent::class);
    }

    /**
     * Get the chat conversation for this order.
     */
    public function chatConversation()
    {
        return $this->hasOne(ChatConversation::class);
    }

    /**
     * Get the driver locations for this order.
     */
    public function driverLocations()
    {
        return $this->hasMany(DriverLocation::class);
    }

    /**
     * Absolute URLs for proof-of-delivery images (customer/rider apps).
     */
    public function getProofImageUrlsAttribute(): array
    {
        $images = $this->proof_images ?? [];
        if (!is_array($images)) {
            return [];
        }

        return array_values(array_filter(array_map(function ($path) {
            if ($path === null || $path === '') {
                return null;
            }

            $value = trim((string) $path);
            if ($value === '') {
                return null;
            }

            if (str_starts_with($value, 'http://') || str_starts_with($value, 'https://')) {
                return $value;
            }

            $normalized = ltrim(str_replace('\\', '/', $value), '/');
            if (preg_match('#^proof_images/(\d+)/([^/]+)$#', $normalized, $matches)) {
                return url('/api/v1/proof-images/' . $matches[1] . '/' . $matches[2]);
            }

            return url('/api/v1/storage/' . $normalized);
        }, $images)));
    }
}
