<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AuditLogger
{
    /**
     * Log user actions for audit trail.
     */
    public static function log(
        string $action,
        ?int $userId = null,
        ?string $model = null,
        ?int $modelId = null,
        ?array $changes = null,
        ?string $ipAddress = null,
        ?string $userAgent = null
    ): void {
        try {
            DB::table('audit_logs')->insert([
                'user_id' => $userId,
                'action' => $action,
                'model' => $model,
                'model_id' => $modelId,
                'changes' => $changes ? json_encode($changes) : null,
                'ip_address' => $ipAddress ?? request()->ip(),
                'user_agent' => $userAgent ?? request()->userAgent(),
                'created_at' => now(),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to write audit log', [
                'error' => $e->getMessage(),
                'action' => $action,
            ]);
        }
    }

    /**
     * Log authentication events.
     */
    public static function logAuth(string $event, ?int $userId = null, bool $success = true): void
    {
        self::log(
            action: "auth.{$event}",
            userId: $userId,
            changes: ['success' => $success]
        );
    }

    /**
     * Log data access events.
     */
    public static function logAccess(string $resource, int $resourceId, int $userId): void
    {
        self::log(
            action: "access.{$resource}",
            userId: $userId,
            model: $resource,
            modelId: $resourceId
        );
    }

    /**
     * Log data modification events.
     */
    public static function logModification(
        string $action,
        string $model,
        int $modelId,
        array $oldData,
        array $newData,
        int $userId
    ): void {
        $changes = [
            'old' => $oldData,
            'new' => $newData,
        ];

        self::log(
            action: "{$action}.{$model}",
            userId: $userId,
            model: $model,
            modelId: $modelId,
            changes: $changes
        );
    }
}
