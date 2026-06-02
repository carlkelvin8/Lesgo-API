<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'name',
        'email',
        'google_id',
        'phone_number',
        'date_of_birth',
        'address_line1',
        'address_line2',
        'profile_photo_url',
        'profile_picture',
        'referral_code',
        'referred_by',
        'points',
        'password',
        'role',
        'is_active',
        'deactivated_at',
        'deactivation_reason',
        'fcm_token',
        'phone_verified_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at'  => 'datetime',
        'phone_verified_at'  => 'datetime',
        'date_of_birth'      => 'date',
        'points'             => 'integer',
        'is_active'          => 'boolean',
        'deactivated_at'     => 'datetime',
        'password'           => 'hashed',
        'created_at'         => 'datetime',
        'updated_at'         => 'datetime',
    ];

    // Roles helpers
    public function isCustomer(): bool
    {
        return $this->role === 'customer';
    }

    public function isDriver(): bool
    {
        return $this->role === 'driver';
    }

    public function isPartnerAdmin(): bool
    {
        return $this->role === 'partner_admin';
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    // Relationships

    public function partner()
    {
        return $this->hasOne(Partner::class, 'user_id');
    }

    public function addresses()
    {
        return $this->hasMany(Address::class, 'user_id');
    }

    public function customerProfile()
    {
        return $this->hasOne(CustomerProfile::class, 'user_id');
    }

    public function driverProfile()
    {
        return $this->hasOne(DriverProfile::class, 'user_id');
    }

    public function wallet()
    {
        return $this->hasOne(Wallet::class, 'user_id');
    }

    public function customerOrders()
    {
        return $this->hasMany(Order::class, 'customer_id');
    }

    public function payments()
    {
        return $this->hasMany(Payment::class, 'customer_id');
    }

    public function createdWalletTransactions()
    {
        return $this->hasMany(WalletTransaction::class, 'created_by');
    }

    // Customer Experience relationships

    public function reviews()
    {
        return $this->hasMany(RatingReview::class, 'user_id');
    }

    public function receivedReviews()
    {
        return $this->hasMany(RatingReview::class, 'driver_id');
    }

    public function supportTickets()
    {
        return $this->hasMany(SupportTicket::class, 'user_id');
    }

    public function assignedTickets()
    {
        return $this->hasMany(SupportTicket::class, 'assigned_agent_id');
    }

    public function userSessions()
    {
        return $this->hasMany(UserSession::class, 'user_id');
    }

    public function documentVerifications()
    {
        return $this->hasMany(DocumentVerification::class, 'user_id');
    }

    public function verifiedDocuments()
    {
        return $this->hasMany(DocumentVerification::class, 'verified_by');
    }

    public function socialShares()
    {
        return $this->hasMany(SocialShare::class, 'user_id');
    }

    // Geofencing relationships

    public function geofences()
    {
        return $this->hasMany(Geofence::class, 'created_by');
    }

    public function geofenceEvents()
    {
        return $this->hasMany(GeofenceEvent::class, 'user_id');
    }

    // Real-time system relationships

    public function chatConversationsAsCustomer()
    {
        return $this->hasMany(ChatConversation::class, 'customer_id');
    }

    public function chatConversationsAsDriver()
    {
        return $this->hasMany(ChatConversation::class, 'driver_id');
    }

    public function chatMessages()
    {
        return $this->hasMany(ChatMessage::class, 'sender_id');
    }

    public function driverLocations()
    {
        return $this->hasMany(DriverLocation::class, 'driver_id');
    }

    public function realtimeNotifications()
    {
        return $this->hasMany(RealtimeNotification::class, 'user_id');
    }

    public function websocketConnections()
    {
        return $this->hasMany(WebSocketConnection::class, 'user_id');
    }

    public function refreshTokens()
    {
        return $this->hasMany(RefreshToken::class);
    }

    // Security helper methods

    public function hasRole(string $role): bool
    {
        return $this->role === $role;
    }

    public function hasAnyRole(array $roles): bool
    {
        return in_array($this->role, $roles);
    }
}
