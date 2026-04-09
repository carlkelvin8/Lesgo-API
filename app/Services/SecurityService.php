<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\SecurityEvent;
use App\Models\SecuritySetting;
use App\Models\IpBlacklist;
use App\Models\IpWhitelist;
use App\Models\PaymentSecurityLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class SecurityService
{
    /**
     * Log an audit event
     */
    public function logAuditEvent(array $data): AuditLog
    {
        $auditData = array_merge([
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'session_id' => session()->getId(),
            'request_id' => request()->header('X-Request-ID'),
            'occurred_at' => now(),
        ], $data);

        return AuditLog::create($auditData);
    }

    /**
     * Log a security event
     */
    public function logSecurityEvent(array $data): SecurityEvent
    {
        $eventData = array_merge([
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'detected_at' => now(),
        ], $data);

        $event = SecurityEvent::create($eventData);

        // Log critical events to system log
        if ($event->severity === 'critical') {
            Log::critical('Critical security event detected', [
                'event_id' => $event->id,
                'event_type' => $event->event_type,
                'description' => $event->description,
                'ip_address' => $event->ip_address,
            ]);
        }

        return $event;
    }

    /**
     * Log payment security event for PCI DSS compliance
     */
    public function logPaymentSecurityEvent(array $data): PaymentSecurityLog
    {
        $logData = array_merge([
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'processed_at' => now(),
            'is_compliant' => true,
        ], $data);

        return PaymentSecurityLog::create($logData);
    }

    /**
     * Check if IP address is allowed
     */
    public function isIpAllowed(string $ip): bool
    {
        // Check blacklist first
        if (IpBlacklist::isBlacklisted($ip)) {
            $this->logSecurityEvent([
                'event_type' => 'blocked_ip_access',
                'severity' => 'warning',
                'source' => 'ip_filter',
                'description' => "Access attempt from blacklisted IP: {$ip}",
                'event_data' => ['ip' => $ip, 'reason' => 'blacklisted'],
            ]);
            return false;
        }

        // If whitelist is enabled, check whitelist
        if (SecuritySetting::getValue('ip_whitelist_enabled', false)) {
            if (!IpWhitelist::isWhitelisted($ip)) {
                $this->logSecurityEvent([
                    'event_type' => 'non_whitelisted_access',
                    'severity' => 'warning',
                    'source' => 'ip_filter',
                    'description' => "Access attempt from non-whitelisted IP: {$ip}",
                    'event_data' => ['ip' => $ip, 'reason' => 'not_whitelisted'],
                ]);
                return false;
            }
        }

        return true;
    }

    /**
     * Analyze request for suspicious patterns
     */
    public function analyzeSuspiciousActivity(Request $request): array
    {
        $suspiciousPatterns = [];
        $payload = json_encode($request->all());

        // SQL injection patterns
        $sqlPatterns = [
            '/(\bUNION\b.*\bSELECT\b)/i' => 'SQL Injection - UNION SELECT',
            '/(\bDROP\b.*\bTABLE\b)/i' => 'SQL Injection - DROP TABLE',
            '/(\bINSERT\b.*\bINTO\b)/i' => 'SQL Injection - INSERT INTO',
            '/(\bDELETE\b.*\bFROM\b)/i' => 'SQL Injection - DELETE FROM',
            '/(\bUPDATE\b.*\bSET\b)/i' => 'SQL Injection - UPDATE SET',
            '/(--|\#|\/\*|\*\/)/i' => 'SQL Injection - Comments',
        ];

        // XSS patterns
        $xssPatterns = [
            '/<script[^>]*>.*?<\/script>/is' => 'XSS - Script tags',
            '/javascript:/i' => 'XSS - JavaScript protocol',
            '/on\w+\s*=/i' => 'XSS - Event handlers',
        ];

        // Path traversal patterns
        $pathPatterns = [
            '/\.\.[\/\\\\]/i' => 'Path Traversal',
        ];

        $allPatterns = array_merge($sqlPatterns, $xssPatterns, $pathPatterns);

        foreach ($allPatterns as $pattern => $description) {
            if (preg_match($pattern, $payload)) {
                $suspiciousPatterns[] = $description;
            }
        }

        // Check for suspicious headers
        $suspiciousHeaders = [
            'X-Forwarded-For' => 'Potential proxy bypass',
            'X-Real-IP' => 'Potential IP spoofing',
            'X-Originating-IP' => 'Potential IP spoofing',
        ];

        foreach ($suspiciousHeaders as $header => $description) {
            if ($request->hasHeader($header)) {
                $suspiciousPatterns[] = $description;
            }
        }

        return $suspiciousPatterns;
    }

    /**
     * Handle suspicious activity detection
     */
    public function handleSuspiciousActivity(Request $request, array $patterns): void
    {
        if (empty($patterns)) {
            return;
        }

        $this->logSecurityEvent([
            'event_type' => 'suspicious_activity',
            'severity' => 'warning',
            'source' => 'pattern_detection',
            'description' => 'Suspicious patterns detected in request: ' . implode(', ', $patterns),
            'event_data' => [
                'patterns' => $patterns,
                'url' => $request->fullUrl(),
                'method' => $request->method(),
                'payload' => $request->except(['password', 'password_confirmation']),
            ],
        ]);

        // Auto-block IP if too many suspicious activities
        $recentEvents = SecurityEvent::where('ip_address', $request->ip())
            ->where('event_type', 'suspicious_activity')
            ->where('detected_at', '>=', now()->subHour())
            ->count();

        if ($recentEvents >= 5) {
            IpBlacklist::create([
                'ip_address' => $request->ip(),
                'reason' => 'suspicious_activity',
                'description' => 'Auto-blocked due to repeated suspicious activity',
                'expires_at' => now()->addHours(24),
                'created_by' => 'system',
            ]);

            $this->logSecurityEvent([
                'event_type' => 'auto_ip_block',
                'severity' => 'error',
                'source' => 'auto_protection',
                'description' => "IP {$request->ip()} auto-blocked due to repeated suspicious activity",
                'event_data' => ['ip' => $request->ip(), 'event_count' => $recentEvents],
            ]);
        }
    }

    /**
     * Get security dashboard data
     */
    public function getSecurityDashboard(): array
    {
        return [
            'recent_events' => SecurityEvent::recent(24)->count(),
            'critical_events' => SecurityEvent::critical()->unresolved()->count(),
            'failed_logins' => AuditLog::where('event_type', 'login_failed')
                ->where('occurred_at', '>=', now()->subDay())
                ->count(),
            'suspicious_activities' => SecurityEvent::where('event_type', 'suspicious_activity')
                ->recent(24)
                ->count(),
            'blocked_ips' => IpBlacklist::active()->count(),
            'whitelisted_ips' => IpWhitelist::active()->count(),
            'payment_compliance' => PaymentSecurityLog::recent(24)
                ->selectRaw('is_compliant, COUNT(*) as count')
                ->groupBy('is_compliant')
                ->pluck('count', 'is_compliant')
                ->toArray(),
        ];
    }
}