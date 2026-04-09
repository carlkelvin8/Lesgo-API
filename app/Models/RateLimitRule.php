<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RateLimitRule extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'endpoint_pattern',
        'method',
        'max_attempts',
        'window_minutes',
        'scope',
        'is_active',
        'priority',
        'conditions',
        'metadata',
    ];

    protected $casts = [
        'conditions' => 'array',
        'metadata' => 'array',
        'is_active' => 'boolean',
        'max_attempts' => 'integer',
        'window_minutes' => 'integer',
        'priority' => 'integer',
    ];

    /**
     * Scope for active rules
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope ordered by priority
     */
    public function scopeByPriority($query)
    {
        return $query->orderBy('priority', 'desc');
    }

    /**
     * Check if this rule matches the given request
     */
    public function matches(string $endpoint, string $method = null): bool
    {
        if (!$this->is_active) {
            return false;
        }

        // Check endpoint pattern
        if (!fnmatch($this->endpoint_pattern, $endpoint)) {
            return false;
        }

        // Check method if specified
        if ($this->method && strtoupper($this->method) !== strtoupper($method)) {
            return false;
        }

        return true;
    }

    /**
     * Get the rate limit key for this rule
     */
    public function getRateLimitKey(string $identifier): string
    {
        return "rate_limit:{$this->id}:{$this->scope}:{$identifier}";
    }
}