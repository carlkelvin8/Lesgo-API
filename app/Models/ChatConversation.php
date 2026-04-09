<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ChatConversation extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'customer_id',
        'driver_id',
        'status',
        'last_message_at',
        'metadata',
    ];

    protected $casts = [
        'last_message_at' => 'datetime',
        'metadata' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // Relationships

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function customer()
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    public function driver()
    {
        return $this->belongsTo(User::class, 'driver_id');
    }

    public function messages()
    {
        return $this->hasMany(ChatMessage::class, 'conversation_id');
    }

    public function latestMessage()
    {
        return $this->hasOne(ChatMessage::class, 'conversation_id')->latest();
    }

    public function unreadMessages()
    {
        return $this->hasMany(ChatMessage::class, 'conversation_id')->whereNull('read_at');
    }

    // Scopes

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeForUser($query, int $userId)
    {
        return $query->where(function ($q) use ($userId) {
            $q->where('customer_id', $userId)
              ->orWhere('driver_id', $userId);
        });
    }

    // Helper methods

    public function getOtherParticipant(int $userId): ?User
    {
        if ($this->customer_id === $userId) {
            return $this->driver;
        } elseif ($this->driver_id === $userId) {
            return $this->customer;
        }
        
        return null;
    }

    public function markAsEnded(): void
    {
        $this->update(['status' => 'ended']);
    }

    public function updateLastMessageTime(): void
    {
        $this->update(['last_message_at' => now()]);
    }
}