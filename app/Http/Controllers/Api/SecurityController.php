<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\SecurityService;
use App\Services\TwoFactorAuthService;
use App\Services\BiometricAuthService;
use App\Services\GdprService;
use App\Models\AuditLog;
use App\Models\SecurityEvent;
use App\Models\GdprRequest;
use App\Models\IpWhitelist;
use App\Models\IpBlacklist;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class SecurityController extends Controller
{
    private SecurityService $securityService;
    private TwoFactorAuthService $twoFactorService;
    private BiometricAuthService $biometricService;
    private GdprService $gdprService;

    public function __construct(
        SecurityService $securityService,
        TwoFactorAuthService $twoFactorService,
        BiometricAuthService $biometricService,
        GdprService $gdprService
    ) {
        $this->securityService = $securityService;
        $this->twoFactorService = $twoFactorService;
        $this->biometricService = $biometricService;
        $this->gdprService = $gdprService;
    }

    /**
     * Get security dashboard data
     */
    public function dashboard(): JsonResponse
    {
        $data = $this->securityService->getSecurityDashboard();

        return response()->json([
            'success' => true,
            'message' => 'Security dashboard data retrieved successfully',
            'request_id' => request()->header('X-Request-ID'),
            'data' => $data,
        ]);
    }

    /**
     * Setup 2FA TOTP
     */
    public function setup2FA(): JsonResponse
    {
        $user = Auth::user();
        $setup = $this->twoFactorService->enableTotp($user);

        return response()->json([
            'success' => true,
            'message' => '2FA setup initiated. Please verify with the code from your authenticator app.',
            'request_id' => request()->header('X-Request-ID'),
            'data' => [
                'secret' => $setup['secret'],
                'qr_code_url' => $setup['qr_code_url'],
                'backup_codes' => $setup['backup_codes'],
            ],
        ]);
    }

    /**
     * Verify and enable 2FA
     */
    public function verify2FA(Request $request): JsonResponse
    {
        $request->validate([
            'code' => 'required|string|size:6',
        ]);

        $user = Auth::user();
        $verified = $this->twoFactorService->verifyAndEnableTotp($user, $request->code);

        if ($verified) {
            return response()->json([
                'success' => true,
                'message' => '2FA enabled successfully',
                'request_id' => request()->header('X-Request-ID'),
                'data' => ['2fa_enabled' => true],
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Invalid verification code',
            'request_id' => request()->header('X-Request-ID'),
        ], 400);
    }

    /**
     * Disable 2FA
     */
    public function disable2FA(): JsonResponse
    {
        $user = Auth::user();
        $disabled = $this->twoFactorService->disable2FA($user);

        if ($disabled) {
            return response()->json([
                'success' => true,
                'message' => '2FA disabled successfully',
                'request_id' => request()->header('X-Request-ID'),
                'data' => ['2fa_enabled' => false],
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => '2FA not found or already disabled',
            'request_id' => request()->header('X-Request-ID'),
        ], 400);
    }

    /**
     * Regenerate backup codes
     */
    public function regenerateBackupCodes(): JsonResponse
    {
        $user = Auth::user();
        $codes = $this->twoFactorService->regenerateBackupCodes($user);

        if (!empty($codes)) {
            return response()->json([
                'success' => true,
                'message' => 'Backup codes regenerated successfully',
                'request_id' => request()->header('X-Request-ID'),
                'data' => ['backup_codes' => $codes],
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => '2FA not enabled',
            'request_id' => request()->header('X-Request-ID'),
        ], 400);
    }

    /**
     * Enroll biometric authentication
     */
    public function enrollBiometric(Request $request): JsonResponse
    {
        $request->validate([
            'biometric_type' => 'required|in:fingerprint,face_id,voice,iris',
            'device_id' => 'required|string',
            'biometric_template' => 'required|string',
            'public_key' => 'nullable|string',
            'device_info' => 'nullable|array',
        ]);

        $user = Auth::user();
        $biometric = $this->biometricService->enrollBiometric($user, $request->all());

        return response()->json([
            'success' => true,
            'message' => 'Biometric authentication enrolled successfully',
            'request_id' => request()->header('X-Request-ID'),
            'data' => [
                'biometric_id' => $biometric->id,
                'biometric_type' => $biometric->biometric_type,
                'device_id' => $biometric->device_id,
                'enrolled_at' => $biometric->enrolled_at,
            ],
        ]);
    }

    /**
     * Verify biometric authentication
     */
    public function verifyBiometric(Request $request): JsonResponse
    {
        $request->validate([
            'biometric_type' => 'required|in:fingerprint,face_id,voice,iris',
            'device_id' => 'required|string',
            'biometric_template' => 'required|string',
        ]);

        $user = Auth::user();
        $verified = $this->biometricService->verifyBiometric($user, $request->all());

        if ($verified) {
            return response()->json([
                'success' => true,
                'message' => 'Biometric authentication verified successfully',
                'request_id' => request()->header('X-Request-ID'),
                'data' => ['verified' => true],
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Biometric verification failed',
            'request_id' => request()->header('X-Request-ID'),
        ], 401);
    }

    /**
     * Get user's biometric authentications
     */
    public function getBiometrics(): JsonResponse
    {
        $user = Auth::user();
        $biometrics = $this->biometricService->getUserBiometrics($user);

        return response()->json([
            'success' => true,
            'message' => 'Biometric authentications retrieved successfully',
            'request_id' => request()->header('X-Request-ID'),
            'data' => $biometrics,
        ]);
    }

    /**
     * Deactivate biometric authentication
     */
    public function deactivateBiometric(Request $request): JsonResponse
    {
        $request->validate([
            'biometric_id' => 'required|integer',
        ]);

        $user = Auth::user();
        $deactivated = $this->biometricService->deactivateBiometric($user, $request->biometric_id);

        if ($deactivated) {
            return response()->json([
                'success' => true,
                'message' => 'Biometric authentication deactivated successfully',
                'request_id' => request()->header('X-Request-ID'),
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Biometric authentication not found',
            'request_id' => request()->header('X-Request-ID'),
        ], 404);
    }

    /**
     * Get audit logs
     */
    public function getAuditLogs(Request $request): JsonResponse
    {
        $query = AuditLog::with('user:id,name,email')
            ->orderBy('occurred_at', 'desc');

        if ($request->user_id) {
            $query->where('user_id', $request->user_id);
        }

        if ($request->event_type) {
            $query->where('event_type', $request->event_type);
        }

        if ($request->risk_level) {
            $query->where('risk_level', $request->risk_level);
        }

        if ($request->from_date) {
            $query->where('occurred_at', '>=', $request->from_date);
        }

        if ($request->to_date) {
            $query->where('occurred_at', '<=', $request->to_date);
        }

        $logs = $query->paginate(50);

        return response()->json([
            'success' => true,
            'message' => 'Audit logs retrieved successfully',
            'request_id' => request()->header('X-Request-ID'),
            'data' => $logs,
        ]);
    }

    /**
     * Get security events
     */
    public function getSecurityEvents(Request $request): JsonResponse
    {
        $query = SecurityEvent::with('user:id,name,email')
            ->orderBy('detected_at', 'desc');

        if ($request->severity) {
            $query->where('severity', $request->severity);
        }

        if ($request->event_type) {
            $query->where('event_type', $request->event_type);
        }

        if ($request->is_resolved !== null) {
            $query->where('is_resolved', $request->boolean('is_resolved'));
        }

        $events = $query->paginate(50);

        return response()->json([
            'success' => true,
            'message' => 'Security events retrieved successfully',
            'request_id' => request()->header('X-Request-ID'),
            'data' => $events,
        ]);
    }

    /**
     * Resolve security event
     */
    public function resolveSecurityEvent(Request $request, int $eventId): JsonResponse
    {
        $request->validate([
            'resolution_notes' => 'nullable|string',
        ]);

        $event = SecurityEvent::findOrFail($eventId);
        $event->resolve(Auth::user()->name, $request->resolution_notes);

        return response()->json([
            'success' => true,
            'message' => 'Security event resolved successfully',
            'request_id' => request()->header('X-Request-ID'),
        ]);
    }

    /**
     * Create GDPR request
     */
    public function createGdprRequest(Request $request): JsonResponse
    {
        $request->validate([
            'request_type' => 'required|in:access,portability,rectification,erasure,restriction',
            'description' => 'nullable|string',
            'requested_data' => 'nullable|array',
        ]);

        $user = Auth::user();
        $gdprRequest = $this->gdprService->createDataRequest($user, $request->request_type, $request->all());

        return response()->json([
            'success' => true,
            'message' => 'GDPR request created successfully. Please check your email for verification.',
            'request_id' => request()->header('X-Request-ID'),
            'data' => [
                'request_id' => $gdprRequest->id,
                'request_type' => $gdprRequest->request_type,
                'status' => $gdprRequest->status,
            ],
        ]);
    }

    /**
     * Get user's GDPR requests
     */
    public function getGdprRequests(): JsonResponse
    {
        $user = Auth::user();
        $requests = GdprRequest::where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'GDPR requests retrieved successfully',
            'request_id' => request()->header('X-Request-ID'),
            'data' => $requests,
        ]);
    }

    /**
     * Get IP whitelist (admin only)
     */
    public function getIpWhitelist(): JsonResponse
    {
        $whitelist = IpWhitelist::orderBy('created_at', 'desc')->paginate(50);

        return response()->json([
            'success' => true,
            'message' => 'IP whitelist retrieved successfully',
            'request_id' => request()->header('X-Request-ID'),
            'data' => $whitelist,
        ]);
    }

    /**
     * Add IP to whitelist (admin only)
     */
    public function addToWhitelist(Request $request): JsonResponse
    {
        $request->validate([
            'ip_address' => 'required|ip',
            'type' => 'required|in:permanent,temporary,api_access',
            'description' => 'nullable|string',
            'expires_at' => 'nullable|date',
        ]);

        $whitelist = IpWhitelist::create([
            'ip_address' => $request->ip_address,
            'type' => $request->type,
            'description' => $request->description,
            'expires_at' => $request->expires_at,
            'created_by' => Auth::user()->name,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'IP added to whitelist successfully',
            'request_id' => request()->header('X-Request-ID'),
            'data' => $whitelist,
        ]);
    }

    /**
     * Get IP blacklist (admin only)
     */
    public function getIpBlacklist(): JsonResponse
    {
        $blacklist = IpBlacklist::orderBy('created_at', 'desc')->paginate(50);

        return response()->json([
            'success' => true,
            'message' => 'IP blacklist retrieved successfully',
            'request_id' => request()->header('X-Request-ID'),
            'data' => $blacklist,
        ]);
    }

    /**
     * Add IP to blacklist (admin only)
     */
    public function addToBlacklist(Request $request): JsonResponse
    {
        $request->validate([
            'ip_address' => 'required|ip',
            'reason' => 'required|in:suspicious_activity,abuse,security_threat',
            'description' => 'nullable|string',
            'expires_at' => 'nullable|date',
        ]);

        $blacklist = IpBlacklist::create([
            'ip_address' => $request->ip_address,
            'reason' => $request->reason,
            'description' => $request->description,
            'expires_at' => $request->expires_at,
            'created_by' => Auth::user()->name,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'IP added to blacklist successfully',
            'request_id' => request()->header('X-Request-ID'),
            'data' => $blacklist,
        ]);
    }
}