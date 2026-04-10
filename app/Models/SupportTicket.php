<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SupportTicket extends Model
{
    use HasFactory;

    protected $fillable = [
        'ticket_number',
        'user_id',
        'order_id',
        'assigned_to',
        'subject',
        'description',
        'category',
        'priority',
        'status',
        'first_response_at',
        'resolved_at',
        'closed_at',
        'last_activity_at',
        'satisfaction_rating',
        'satisfaction_comment',
        'metadata',
        'attachments',
    ];

    protected $casts = [
        'first_response_at' => 'datetime',
        'resolved_at' => 'datetime',
        'closed_at' => 'datetime',
        'last_activity_at' => 'datetime',
        'metadata' => 'array',
        'attachments' => 'array',
    ];

    /**
     * Boot the model.
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($ticket) {
            if (!$ticket->ticket_number) {
                $ticket->ticket_number = static::generateTicketNumber();
            }
            $ticket->last_activity_at = now();
        });

        static::updating(function ($ticket) {
            $ticket->last_activity_at = now();
        });
    }

    /**
     * Get the user who created the ticket.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the related order.
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Get the assigned agent.
     */
    public function assignedAgent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /**
     * Get ticket messages.
     */
    public function messages(): HasMany
    {
        return $this->hasMany(SupportTicketMessage::class, 'ticket_id');
    }

    /**
     * Get public messages (visible to customer).
     */
    public function publicMessages(): HasMany
    {
        return $this->messages()->where('is_internal', false);
    }

    /**
     * Get internal messages (staff only).
     */
    public function internalMessages(): HasMany
    {
        return $this->messages()->where('is_internal', true);
    }

    /**
     * Scope for open tickets.
     */
    public function scopeOpen($query)
    {
        return $query->whereIn('status', ['open', 'in_progress', 'waiting_customer', 'waiting_internal']);
    }

    /**
     * Scope for closed tickets.
     */
    public function scopeClosed($query)
    {
        return $query->whereIn('status', ['resolved', 'closed', 'cancelled']);
    }

    /**
     * Scope for high priority tickets.
     */
    public function scopeHighPriority($query)
    {
        return $query->whereIn('priority', ['high', 'urgent']);
    }

    /**
     * Scope for overdue tickets.
     */
    public function scopeOverdue($query)
    {
        return $query->where('created_at', '<', now()->subHours(24))
                    ->whereNull('first_response_at')
                    ->whereIn('status', ['open', 'in_progress']);
    }

    /**
     * Generate unique ticket number.
     */
    public static function generateTicketNumber(): string
    {
        $prefix = 'TKT-' . date('Y') . '-';
        $lastTicket = static::where('ticket_number', 'like', $prefix . '%')
                           ->orderBy('id', 'desc')
                           ->first();

        if ($lastTicket) {
            $lastNumber = (int) substr($lastTicket->ticket_number, strlen($prefix));
            $newNumber = $lastNumber + 1;
        } else {
            $newNumber = 1;
        }

        return $prefix . str_pad($newNumber, 6, '0', STR_PAD_LEFT);
    }

    /**
     * Check if ticket is overdue for first response.
     */
    public function isOverdueForFirstResponse(): bool
    {
        if ($this->first_response_at) {
            return false;
        }

        $slaHours = match ($this->priority) {
            'urgent' => 1,
            'high' => 4,
            'medium' => 24,
            'low' => 48,
        };

        return $this->created_at->addHours($slaHours)->isPast();
    }

    /**
     * Get response time in hours.
     */
    public function getResponseTimeAttribute(): ?float
    {
        if (!$this->first_response_at) {
            return null;
        }

        return $this->created_at->diffInHours($this->first_response_at, true);
    }

    /**
     * Get resolution time in hours.
     */
    public function getResolutionTimeAttribute(): ?float
    {
        if (!$this->resolved_at) {
            return null;
        }

        return $this->created_at->diffInHours($this->resolved_at, true);
    }

    /**
     * Check if ticket can be closed.
     */
    public function canBeClosed(): bool
    {
        return in_array($this->status, ['resolved', 'waiting_customer']);
    }

    /**
     * Close the ticket.
     */
    public function close(): bool
    {
        return $this->update([
            'status' => 'closed',
            'closed_at' => now(),
        ]);
    }

    /**
     * Resolve the ticket.
     */
    public function resolve(): bool
    {
        return $this->update([
            'status' => 'resolved',
            'resolved_at' => now(),
        ]);
    }

    /**
     * Assign ticket to agent.
     */
    public function assignTo(User $agent): bool
    {
        return $this->update([
            'assigned_to' => $agent->id,
            'status' => 'in_progress',
        ]);
    }

    /**
     * Add message to ticket.
     */
    public function addMessage(User $user, string $message, array $attachments = [], bool $isInternal = false): SupportTicketMessage
    {
        $ticketMessage = $this->messages()->create([
            'user_id' => $user->id,
            'message' => $message,
            'attachments' => $attachments,
            'is_internal' => $isInternal,
        ]);

        // Update first response time if this is the first staff response
        if (!$this->first_response_at && $user->role !== 'customer' && !$isInternal) {
            $this->update(['first_response_at' => now()]);
        }

        return $ticketMessage;
    }
}