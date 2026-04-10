<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderTrackingEvent extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'user_id',
        'event_type',
        'event_title',
        'event_description',
        'event_category',
        'latitude',
        'longitude',
        'location_address',
        'metadata',
        'attachments',
        'is_visible_to_customer',
        'is_milestone',
        'event_time',
    ];

    protected $casts = [
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
        'metadata' => 'array',
        'attachments' => 'array',
        'is_visible_to_customer' => 'boolean',
        'is_milestone' => 'boolean',
        'event_time' => 'datetime',
    ];

    /**
     * Get the order this event belongs to.
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Get the user who triggered this event.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope for customer-visible events.
     */
    public function scopeVisibleToCustomer($query)
    {
        return $query->where('is_visible_to_customer', true);
    }

    /**
     * Scope for milestone events.
     */
    public function scopeMilestones($query)
    {
        return $query->where('is_milestone', true);
    }

    /**
     * Scope for events by category.
     */
    public function scopeByCategory($query, string $category)
    {
        return $query->where('event_category', $category);
    }

    /**
     * Scope ordered by event time.
     */
    public function scopeOrdered($query, string $direction = 'asc')
    {
        return $query->orderBy('event_time', $direction);
    }

    /**
     * Get event icon based on type.
     */
    public function getEventIconAttribute(): string
    {
        return match ($this->event_type) {
            'order_created' => '📝',
            'payment_confirmed' => '💳',
            'driver_assigned' => '👨‍💼',
            'driver_en_route' => '🚗',
            'arrived_pickup' => '📍',
            'picked_up' => '📦',
            'in_transit' => '🚛',
            'arrived_destination' => '🏁',
            'delivered' => '✅',
            'cancelled' => '❌',
            'delayed' => '⏰',
            'issue_reported' => '⚠️',
            default => '📋',
        };
    }

    /**
     * Get event color for UI.
     */
    public function getEventColorAttribute(): string
    {
        return match ($this->event_category) {
            'order' => 'blue',
            'payment' => 'green',
            'delivery' => 'orange',
            'system' => 'gray',
            default => 'blue',
        };
    }

    /**
     * Check if event has location data.
     */
    public function hasLocation(): bool
    {
        return !is_null($this->latitude) && !is_null($this->longitude);
    }

    /**
     * Get formatted location.
     */
    public function getFormattedLocationAttribute(): ?array
    {
        if (!$this->hasLocation()) {
            return null;
        }

        return [
            'lat' => (float) $this->latitude,
            'lng' => (float) $this->longitude,
            'address' => $this->location_address,
        ];
    }

    /**
     * Create a tracking event.
     */
    public static function createEvent(
        Order $order,
        string $eventType,
        string $eventTitle,
        ?string $eventDescription = null,
        ?User $user = null,
        array $options = []
    ): self {
        return static::create(array_merge([
            'order_id' => $order->id,
            'user_id' => $user?->id,
            'event_type' => $eventType,
            'event_title' => $eventTitle,
            'event_description' => $eventDescription,
            'event_category' => 'order',
            'is_visible_to_customer' => true,
            'is_milestone' => false,
            'event_time' => now(),
        ], $options));
    }

    /**
     * Common tracking events.
     */
    public static function orderCreated(Order $order, User $user): self
    {
        return static::createEvent(
            $order,
            'order_created',
            'Order Created',
            'Your order has been successfully created and is being processed.',
            $user,
            ['is_milestone' => true, 'event_category' => 'order']
        );
    }

    public static function driverAssigned(Order $order, User $driver): self
    {
        return static::createEvent(
            $order,
            'driver_assigned',
            'Driver Assigned',
            "Driver {$driver->name} has been assigned to your order.",
            $driver,
            ['is_milestone' => true, 'event_category' => 'delivery']
        );
    }

    public static function driverEnRoute(Order $order, User $driver, ?array $location = null): self
    {
        $options = ['event_category' => 'delivery'];
        
        if ($location) {
            $options = array_merge($options, [
                'latitude' => $location['lat'],
                'longitude' => $location['lng'],
                'location_address' => $location['address'] ?? null,
            ]);
        }

        return static::createEvent(
            $order,
            'driver_en_route',
            'Driver En Route',
            "Driver {$driver->name} is on the way to pickup location.",
            $driver,
            $options
        );
    }

    public static function orderDelivered(Order $order, User $driver, ?array $location = null): self
    {
        $options = ['is_milestone' => true, 'event_category' => 'delivery'];
        
        if ($location) {
            $options = array_merge($options, [
                'latitude' => $location['lat'],
                'longitude' => $location['lng'],
                'location_address' => $location['address'] ?? null,
            ]);
        }

        return static::createEvent(
            $order,
            'delivered',
            'Order Delivered',
            'Your order has been successfully delivered.',
            $driver,
            $options
        );
    }
}