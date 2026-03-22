<?php

namespace App\Services;

use App\Jobs\WriteAuditLogJob;
use Illuminate\Support\Facades\Log;

class AuditLogger
{
    /**
     * Queue an audit log entry — never blocks the request cycle.
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
            WriteAuditLogJob::dispatch([
                'user_id'    => $userId,
                'action'     => $action,
                'model'      => $model,
                'model_id'   => $modelId,
                'changes'    => $changes ? json_encode($changes) : null,
                'ip_address' => $ipAddress ?? request()->ip(),
                'user_agent' => $userAgent ?? request()->userAgent(),
                'created_at' => now()->toDateTimeString(),
            ])->onQueue('audit');
        } catch (\Throwable $e) {
            Log::error('AuditLogger: failed to dispatch WriteAuditLogJob', [
                'error'  => $e->getMessage(),
                'action' => $action,
            ]);
        }
    }

    public static function logAuth(string $event, ?int $userId = null, bool $success = true): void
    {
        self::log(
            action: "auth.{$event}",
            userId: $userId,
            changes: ['success' => $success]
        );
    }

    public static function logAccess(string $resource, int $resourceId, int $userId): void
    {
        self::log(
            action: "access.{$resource}",
            userId: $userId,
            model: $resource,
            modelId: $resourceId
        );
    }

    public static function logModification(
        string $action,
        string $model,
        int $modelId,
        array $oldData,
        array $newData,
        int $userId
    ): void {
        self::log(
            action: "{$action}.{$model}",
            userId: $userId,
            model: $model,
            modelId: $modelId,
            changes: ['old' => $oldData, 'new' => $newData]
        );
    }
}
