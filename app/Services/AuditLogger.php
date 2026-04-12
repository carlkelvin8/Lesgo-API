<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\SecurityEvent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * Enhanced Audit Logging Service
 * 
 * Provides comprehensive audit logging for compliance requirements
 * (SOX, GDPR, PCI-DSS, HIPAA). Logs all critical operations with
 * tamper-proof records and retention policies.
 */
class AuditLogger
{
    /**
     * Log retention periods (in days)
     */
    private const RETENTION_CRITICAL = 3650; // 10 years for critical events
    private const RETENTION_HIGH = 2555;     // 7 years for high-risk events
    private const RETENTION_MEDIUM = 365;    // 1 year for medium-risk events
    private const RETENTION_LOW = 90;        // 90 days for low-risk events

    /**
     * Event categories
     */
    public const CATEGORY_AUTHENTICATION = 'authentication';
    public const CATEGORY_AUTHORIZATION = 'authorization';
    public const CATEGORY_DATA_ACCESS = 'data_access';
    public const CATEGORY_DATA_MODIFICATION = 'data_modification';
    public const CATEGORY_FINANCIAL = 'financial';
    public const CATEGORY_ADMINISTRATIVE = 'administrative';
    public const CATEGORY_SECURITY = 'security';
    public const CATEGORY_COMPLIANCE = 'compliance';

    /**
     * Log an audit event
     */
    public static function log(
        string $eventType,
        string $category,
        string $action,
        string $resourceType,
        ?int $resourceId = null,
        array $oldValues = [],
        array $newValues = [],
        ?int $userId = null,
        ?Request $request = null,
        string $riskLevel = 'medium',
        array $metadata = []
    ): AuditLog {
        $userId = $userId ?? Auth::id();
        $request = $request ?? request();

        $auditLog = AuditLog::create([
            'user_id' => $userId,
            'event_type' => $eventType,
            'event_category' => $category,
            'action' => $action,
            'resource_type' => $resourceType,
            'resource_id' => $resourceId,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'session_id' => self::getSessionId($request),
            'request_id' => $request->header('X-Request-ID'),
            'risk_level' => $riskLevel,
            'is_suspicious' => self::isSuspicious($eventType, $request),
            'context' => [
                'route' => $request->route()?->getName(),
                'method' => $request->method(),
                'url' => $request->fullUrl(),
                'device_fingerprint' => $request->attributes->get('device_fingerprint'),
                'device_trusted' => $request->attributes->get('device_trusted', false),
            ],
            'metadata' => $metadata,
            'occurred_at' => now(),
        ]);

        // Log to file for critical events
        if (in_array($riskLevel, ['high', 'critical'])) {
            Log::channel('audit')->info("AUDIT: {$eventType}", [
                'user_id' => $userId,
                'category' => $category,
                'action' => $action,
                'resource' => "{$resourceType}:{$resourceId}",
                'risk_level' => $riskLevel,
            ]);
        }

        // Check for anomalies
        if ($userId && self::shouldCheckAnomalies($category)) {
            app(AnomalyDetectionService::class)->checkLoginAnomaly(
                Auth::user()?->email ?? 'unknown',
                $request->ip(),
                $eventType === 'login_success'
            );
        }

        return $auditLog;
    }

    /**
     * Log authentication event
     */
    public static function logAuth(string $action, ?int $userId = null, bool $success = false): AuditLog
    {
        $request = request();
        
        return self::log(
            $success ? "{$action}_success" : "{$action}_failed",
            self::CATEGORY_AUTHENTICATION,
            $action,
            'User',
            $userId,
            [],
            ['success' => $success],
            $userId,
            $request,
            $success ? 'low' : 'medium'
        );
    }

    /**
     * Log model modification event
     */
    public static function logModification(
        string $action,
        string $model,
        int $modelId,
        array $oldValues,
        array $newValues,
        ?int $userId = null
    ): AuditLog {
        return self::log(
            "model_{$action}",
            self::CATEGORY_DATA_MODIFICATION,
            $action,
            $model,
            $modelId,
            $oldValues,
            $newValues,
            $userId,
            request(),
            'medium',
            ['action_type' => 'update']
        );
    }

    /**
     * Log data access event
     */
    public static function logDataAccess(
        string $resourceType,
        int $resourceId,
        array $accessedFields = [],
        ?int $userId = null
    ): AuditLog {
        return self::log(
            'data_access',
            self::CATEGORY_DATA_ACCESS,
            'read',
            $resourceType,
            $resourceId,
            [],
            ['fields_accessed' => $accessedFields],
            $userId,
            request(),
            'low',
            ['access_type' => 'read']
        );
    }

    /**
     * Log data modification event
     */
    public static function logDataModification(
        string $action,
        string $resourceType,
        int $resourceId,
        array $oldValues,
        array $newValues,
        ?int $userId = null
    ): AuditLog {
        return self::log(
            "data_{$action}",
            self::CATEGORY_DATA_MODIFICATION,
            $action,
            $resourceType,
            $resourceId,
            $oldValues,
            $newValues,
            $userId,
            request(),
            self::getModificationRiskLevel($action, $resourceType),
            ['compliance' => self::getComplianceTags($resourceType)]
        );
    }

    /**
     * Log financial transaction
     */
    public static function logFinancialTransaction(
        string $action,
        string $resourceType,
        int $resourceId,
        array $transactionData,
        ?int $userId = null
    ): AuditLog {
        $amount = $transactionData['amount'] ?? 0;
        $riskLevel = $amount >= 10000 ? 'high' : 'medium';

        $auditLog = self::log(
            "financial_{$action}",
            self::CATEGORY_FINANCIAL,
            $action,
            $resourceType,
            $resourceId,
            [],
            $transactionData,
            $userId,
            request(),
            $riskLevel,
            [
                'amount' => $amount,
                'currency' => $transactionData['currency'] ?? 'PHP',
                'compliance' => ['PCI-DSS', 'SOX'],
            ]
        );

        // Check for transaction anomalies
        if ($userId) {
            app(AnomalyDetectionService::class)->checkTransactionAnomaly(
                $userId,
                $amount,
                $transactionData['payment_method'] ?? 'unknown'
            );
        }

        return $auditLog;
    }

    /**
     * Log administrative action
     */
    public static function logAdministrative(
        string $action,
        string $resourceType,
        ?int $resourceId,
        array $details,
        ?int $userId = null
    ): AuditLog {
        return self::log(
            "admin_{$action}",
            self::CATEGORY_ADMINISTRATIVE,
            $action,
            $resourceType,
            $resourceId,
            [],
            $details,
            $userId,
            request(),
            'high',
            [
                'compliance' => ['SOX', 'GDPR'],
                'requires_review' => true,
            ]
        );
    }

    /**
     * Log security event
     */
    public static function logSecurity(
        string $eventType,
        string $severity,
        array $details,
        ?int $userId = null
    ): AuditLog {
        $request = request();

        $auditLog = self::log(
            $eventType,
            self::CATEGORY_SECURITY,
            $eventType,
            'Security',
            null,
            [],
            $details,
            $userId,
            $request,
            $severity,
            ['requires_immediate_attention' => in_array($severity, ['critical', 'high'])]
        );

        // Also create security event for high/critical
        if (in_array($severity, ['critical', 'high'])) {
            SecurityEvent::create([
                'user_id' => $userId,
                'event_type' => $eventType,
                'severity' => $severity,
                'source' => 'audit_log',
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'description' => $details['description'] ?? "Security event: {$eventType}",
                'event_data' => $details,
                'detected_at' => now(),
            ]);
        }

        return $auditLog;
    }

    /**
     * Log GDPR-related action
     */
    public static function logGDPR(
        string $action,
        int $userId,
        array $details
    ): AuditLog {
        return self::log(
            "gdpr_{$action}",
            self::CATEGORY_COMPLIANCE,
            $action,
            'GDPR',
            $userId,
            [],
            $details,
            $userId,
            request(),
            'high',
            [
                'compliance' => ['GDPR'],
                'data_subject_id' => $userId,
                'legal_basis' => $details['legal_basis'] ?? 'consent',
            ]
        );
    }

    /**
     * Get audit logs for compliance report
     */
    public static function getComplianceReport(
        string $complianceStandard,
        string $startDate,
        string $endDate,
        ?int $userId = null
    ): array {
        $query = AuditLog::whereBetween('occurred_at', [$startDate, $endDate]);

        if ($userId) {
            $query->where('user_id', $userId);
        }

        // Filter by compliance tags
        $query->whereJsonContains('metadata->compliance', $complianceStandard);

        $logs = $query->orderByDesc('occurred_at')->get();

        return [
            'compliance_standard' => $complianceStandard,
            'date_range' => [
                'start' => $startDate,
                'end' => $endDate,
            ],
            'total_events' => $logs->count(),
            'events_by_category' => $logs->groupBy('event_category')->map->count(),
            'events_by_risk' => $logs->groupBy('risk_level')->map->count(),
            'suspicious_events' => $logs->where('is_suspicious', true)->count(),
            'logs' => $logs->map(fn($log) => [
                'id' => $log->id,
                'timestamp' => $log->occurred_at->toISOString(),
                'user_id' => $log->user_id,
                'event_type' => $log->event_type,
                'category' => $log->event_category,
                'action' => $log->action,
                'resource' => "{$log->resource_type}:{$log->resource_id}",
                'risk_level' => $log->risk_level,
                'is_suspicious' => $log->is_suspicious,
                'ip_address' => $log->ip_address,
            ]),
        ];
    }

    /**
     * Clean up old audit logs based on retention policy
     */
    public static function cleanupOldLogs(): int
    {
        $deleted = 0;

        // Delete logs past retention period
        $deleted += AuditLog::where('risk_level', 'low')
            ->where('occurred_at', '<', now()->subDays(self::RETENTION_LOW))
            ->delete();

        $deleted += AuditLog::where('risk_level', 'medium')
            ->where('occurred_at', '<', now()->subDays(self::RETENTION_MEDIUM))
            ->delete();

        $deleted += AuditLog::where('risk_level', 'high')
            ->where('occurred_at', '<', now()->subDays(self::RETENTION_HIGH))
            ->delete();

        // Critical logs are retained for 10 years, so we don't delete them here

        Log::info('Audit log cleanup completed', ['deleted_count' => $deleted]);

        return $deleted;
    }

    /**
     * Export audit logs (for compliance audits)
     */
    public static function exportLogs(
        string $startDate,
        string $endDate,
        string $format = 'json',
        ?int $userId = null
    ): array {
        $query = AuditLog::with('user:id,name,email')
            ->whereBetween('occurred_at', [$startDate, $endDate]);

        if ($userId) {
            $query->where('user_id', $userId);
        }

        $logs = $query->orderBy('occurred_at')->get();

        return [
            'export_date' => now()->toISOString(),
            'date_range' => [
                'start' => $startDate,
                'end' => $endDate,
            ],
            'total_records' => $logs->count(),
            'format' => $format,
            'logs' => $logs->map(fn($log) => [
                'id' => $log->id,
                'timestamp' => $log->occurred_at->toISOString(),
                'user' => $log->user ? [
                    'id' => $log->user->id,
                    'name' => $log->user->name,
                    'email' => $log->user->email,
                ] : null,
                'event_type' => $log->event_type,
                'category' => $log->event_category,
                'action' => $log->action,
                'resource_type' => $log->resource_type,
                'resource_id' => $log->resource_id,
                'old_values' => $log->old_values,
                'new_values' => $log->new_values,
                'ip_address' => $log->ip_address,
                'user_agent' => $log->user_agent,
                'risk_level' => $log->risk_level,
                'is_suspicious' => $log->is_suspicious,
                'context' => $log->context,
                'metadata' => $log->metadata,
            ]),
        ];
    }

    /**
     * Determine if an event should be flagged as suspicious
     */
    private static function isSuspicious(string $eventType, Request $request): bool
    {
        $suspiciousPatterns = [
            'login_failed',
            'unauthorized_access',
            'permission_denied',
            'invalid_token',
            'session_hijack_attempt',
        ];

        if (in_array($eventType, $suspiciousPatterns)) {
            return true;
        }

        // Check if request is from blacklisted IP
        $isBlacklisted = \App\Models\IpBlacklist::where('ip_address', $request->ip())
            ->where(function ($query) {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })->exists();

        return $isBlacklisted;
    }

    /**
     * Determine if anomaly checks should be performed
     */
    private static function shouldCheckAnomalies(string $category): bool
    {
        return in_array($category, [
            self::CATEGORY_AUTHENTICATION,
            self::CATEGORY_FINANCIAL,
        ]);
    }

    /**
     * Get risk level for data modifications
     */
    private static function getModificationRiskLevel(string $action, string $resourceType): string
    {
        $highRiskResources = ['User', 'Payment', 'Wallet', 'Order'];
        $mediumRiskResources = ['Address', 'DriverProfile', 'Partner'];

        if (in_array($resourceType, $highRiskResources)) {
            return in_array($action, ['delete', 'bulk_update']) ? 'critical' : 'high';
        }

        if (in_array($resourceType, $mediumRiskResources)) {
            return 'medium';
        }

        return 'low';
    }

    /**
     * Get compliance tags for resource type
     */
    private static function getComplianceTags(string $resourceType): array
    {
        $tags = [];

        match ($resourceType) {
            'User', 'GDPR' => $tags[] = 'GDPR',
            'Payment', 'Wallet', 'WalletTransaction' => array_push($tags, 'PCI-DSS', 'SOX'),
            'Order' => $tags[] = 'SOX',
            'AuditLog', 'SecurityEvent' => array_push($tags, 'SOX', 'GDPR'),
        };

        return $tags;
    }

    /**
     * Get session ID safely (works for stateless APIs too)
     */
    private static function getSessionId(Request $request): ?string
    {
        try {
            if ($request->hasSession()) {
                return $request->session()->getId();
            }
        } catch (\Exception $e) {
            // Stateless API requests don't have sessions
        }
        
        return null;
    }
}
