<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PartnerStaff extends Model
{
    use HasFactory;

    protected $fillable = [
        'partner_id',
        'user_id',
        'role',
        'permissions',
        'is_active',
        'invited_by',
    ];

    protected $casts = [
        'permissions' => 'array',
        'is_active'   => 'boolean',
    ];

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function invitedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isCook(): bool
    {
        return $this->role === 'cook';
    }

    public function isCashier(): bool
    {
        return $this->role === 'cashier';
    }

    public function hasPermission(string $permission): bool
    {
        if ($this->isAdmin()) return true;
        return in_array($permission, $this->permissions ?? []);
    }
}
