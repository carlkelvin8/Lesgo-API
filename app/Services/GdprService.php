<?php

namespace App\Services;

use App\Models\User;
use App\Models\GdprRequest;
use App\Models\DataRetentionPolicy;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class GdprService
{
    private SecurityService $securityService;

    public function __construct(SecurityService $securityService)
    {
        $this->securityService = $securityService;
    }

    /**
     * Create a GDPR data request
     */
    public function createDataRequest(User $user, string $requestType, array $data = []): GdprRequest
    {
        $request = GdprRequest::create([
            'user_id' => $user->id,
            'request_type' => $requestType,
            'status' => 'pending',
            'description' => $data['description'] ?? null,
            'requested_data' => $data['requested_data'] ?? null,
        ]);

        // Generate verification token
        $token = $request->generateVerificationToken();

        $this->securityService->logAuditEvent([
            'user_id' => $user->id,
            'event_type' => 'gdpr_request_created',
            'event_category' => 'data',
            'action' => 'create',
            'resource_type' => 'gdpr_request',
            'resource_id' => $request->id,
            'risk_level' => 'medium',
            'context' => [
                'request_type' => $requestType,
                'verification_token' => $token,
            ],
        ]);

        // Send verification email (in real app)
        // Mail::to($user)->send(new GdprRequestVerification($request, $token));

        return $request;
    }

    /**
     * Verify GDPR request
     */
    public function verifyRequest(string $token): bool
    {
        $request = GdprRequest::where('verification_token', $token)
            ->where('status', 'pending')
            ->first();

        if ($request) {
            $request->verify();

            $this->securityService->logAuditEvent([
                'user_id' => $request->user_id,
                'event_type' => 'gdpr_request_verified',
                'event_category' => 'data',
                'action' => 'update',
                'resource_type' => 'gdpr_request',
                'resource_id' => $request->id,
                'risk_level' => 'low',
            ]);

            return true;
        }

        return false;
    }

    /**
     * Process data access request
     */
    public function processDataAccess(GdprRequest $request): string
    {
        $user = $request->user;
        $userData = $this->collectUserData($user);

        // Create export file
        $filename = "gdpr_export_{$user->id}_" . now()->format('Y-m-d_H-i-s') . '.json';
        $filepath = "gdpr_exports/{$filename}";

        Storage::disk('local')->put($filepath, json_encode($userData, JSON_PRETTY_PRINT));

        $request->complete('system', 'Data export completed', $filepath);

        $this->securityService->logAuditEvent([
            'user_id' => $user->id,
            'event_type' => 'gdpr_data_exported',
            'event_category' => 'data',
            'action' => 'read',
            'resource_type' => 'user_data',
            'resource_id' => $user->id,
            'risk_level' => 'high',
            'context' => [
                'export_file' => $filepath,
                'data_types' => array_keys($userData),
            ],
        ]);

        return $filepath;
    }

    /**
     * Process data erasure request
     */
    public function processDataErasure(GdprRequest $request): void
    {
        $user = $request->user;

        // Anonymize user data instead of hard delete to maintain referential integrity
        $user->update([
            'name' => 'Deleted User',
            'email' => 'deleted_' . Str::random(10) . '@deleted.local',
            'phone' => null,
            'email_verified_at' => null,
            'password' => Hash::make(Str::random(32)),
        ]);

        // Delete sensitive data
        $user->addresses()->delete();
        $user->driverProfile()->delete();
        $user->notifications()->delete();
        $user->twoFactorAuth()->delete();
        $user->biometricAuth()->delete();

        // Anonymize orders (keep for business records but remove personal data)
        $user->orders()->update([
            'customer_name' => 'Deleted User',
            'customer_phone' => null,
            'customer_email' => null,
        ]);

        $request->complete('system', 'User data erased and anonymized');

        $this->securityService->logAuditEvent([
            'user_id' => $user->id,
            'event_type' => 'gdpr_data_erased',
            'event_category' => 'data',
            'action' => 'delete',
            'resource_type' => 'user_data',
            'resource_id' => $user->id,
            'risk_level' => 'critical',
        ]);
    }

    /**
     * Collect all user data for export
     */
    private function collectUserData(User $user): array
    {
        return [
            'user_profile' => $user->only(['id', 'name', 'email', 'phone', 'created_at', 'updated_at']),
            'addresses' => $user->addresses()->get()->toArray(),
            'driver_profile' => $user->driverProfile?->toArray(),
            'orders' => $user->orders()->with(['items', 'payments'])->get()->toArray(),
            'payments' => $user->payments()->get()->toArray(),
            'notifications' => $user->notifications()->get()->toArray(),
            'support_tickets' => $user->supportTickets()->with('messages')->get()->toArray(),
            'ratings_reviews' => $user->ratingsReviews()->get()->toArray(),
            'chat_messages' => $user->chatMessages()->get()->toArray(),
            'audit_logs' => $user->auditLogs()->get()->toArray(),
            'security_events' => $user->securityEvents()->get()->toArray(),
        ];
    }

    /**
     * Apply data retention policies
     */
    public function applyRetentionPolicies(): array
    {
        $policies = DataRetentionPolicy::active()->get();
        $results = [];

        foreach ($policies as $policy) {
            $count = $this->applyRetentionPolicy($policy);
            $results[$policy->data_type] = $count;
        }

        return $results;
    }

    /**
     * Apply a specific retention policy
     */
    private function applyRetentionPolicy(DataRetentionPolicy $policy): int
    {
        $count = 0;
        $cutoffDate = now()->subDays($policy->retention_days);

        switch ($policy->data_type) {
            case 'audit_logs':
                $query = \App\Models\AuditLog::where('occurred_at', '<', $cutoffDate);
                break;
            case 'security_events':
                $query = \App\Models\SecurityEvent::where('detected_at', '<', $cutoffDate);
                break;
            case 'analytics_events':
                $query = \App\Models\AnalyticsEvent::where('occurred_at', '<', $cutoffDate);
                break;
            default:
                return 0;
        }

        if ($policy->deletion_method === 'hard_delete') {
            $count = $query->count();
            $query->delete();
        } elseif ($policy->deletion_method === 'soft_delete') {
            $count = $query->count();
            $query->update(['deleted_at' => now()]);
        } elseif ($policy->deletion_method === 'anonymize') {
            $records = $query->get();
            $count = $records->count();
            
            foreach ($records as $record) {
                $record->update([
                    'user_id' => null,
                    'ip_address' => '0.0.0.0',
                    'user_agent' => 'anonymized',
                ]);
            }
        }

        $this->securityService->logAuditEvent([
            'event_type' => 'data_retention_applied',
            'event_category' => 'system',
            'action' => 'delete',
            'resource_type' => $policy->data_type,
            'risk_level' => 'low',
            'context' => [
                'policy_id' => $policy->id,
                'deletion_method' => $policy->deletion_method,
                'records_affected' => $count,
                'cutoff_date' => $cutoffDate,
            ],
        ]);

        return $count;
    }

    /**
     * Get GDPR compliance status
     */
    public function getComplianceStatus(): array
    {
        return [
            'pending_requests' => GdprRequest::pending()->count(),
            'processing_requests' => GdprRequest::processing()->count(),
            'completed_requests' => GdprRequest::where('status', 'completed')
                ->where('processed_at', '>=', now()->subMonth())
                ->count(),
            'active_policies' => DataRetentionPolicy::active()->count(),
            'expired_exports' => GdprRequest::whereNotNull('export_file_path')
                ->where('export_expires_at', '<', now())
                ->count(),
        ];
    }
}