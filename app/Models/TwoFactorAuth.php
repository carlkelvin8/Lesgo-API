<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TwoFactorAuth extends Model
{
    use HasFactory;

    protected $table = 'two_factor_auth';

    protected $fillable = [
        'user_id',
        'method',
        'secret',
        'backup_codes',
        'phone_number',
        'is_enabled',
        'enabled_at',
        'last_used_at',
        'recovery_codes',
        'metadata',
    ];

    protected $casts = [
        'backup_codes' => 'array',
        'recovery_codes' => 'array',
        'metadata' => 'array',
        'is_enabled' => 'boolean',
        'enabled_at' => 'datetime',
        'last_used_at' => 'datetime',
    ];

    protected $hidden = [
        'secret',
        'backup_codes',
        'recovery_codes',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Check if this 2FA method is active and enabled
     */
    public function isActive(): bool
    {
        return $this->is_enabled && !empty($this->secret);
    }

    /**
     * Generate new backup codes
     */
    public function generateBackupCodes(int $count = 8): array
    {
        $codes = [];
        for ($i = 0; $i < $count; $i++) {
            $codes[] = strtoupper(substr(str_shuffle('ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'), 0, 8));
        }
        
        $this->backup_codes = $codes;
        $this->save();
        
        return $codes;
    }

    /**
     * Use a backup code
     */
    public function useBackupCode(string $code): bool
    {
        $codes = $this->backup_codes ?? [];
        $key = array_search(strtoupper($code), $codes);
        
        if ($key !== false) {
            unset($codes[$key]);
            $this->backup_codes = array_values($codes);
            $this->last_used_at = now();
            $this->save();
            return true;
        }
        
        return false;
    }
}