<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AuthenticationService;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class GoogleAuthController extends Controller
{
    public function __construct(
        private AuthenticationService $authService
    ) {}

    /**
     * Sign in / register with Google ID token.
     *
     * The Flutter app uses google_sign_in to get an idToken, then sends it here.
     * We verify it against Google's tokeninfo endpoint, then find-or-create the user.
     *
     * POST /api/v1/auth/google
     * Body: { "id_token": "...", "device_name": "mobile-app" }
     */
    public function handleGoogleSignIn(Request $request): JsonResponse
    {
        $request->validate([
            'id_token'    => 'required|string',
            'device_name' => 'nullable|string|max:100',
        ]);

        $idToken    = $request->input('id_token');
        $deviceName = $request->input('device_name', 'mobile-app');

        // ── Verify the ID token with Google ──────────────────────────────────
        $googleUser = $this->verifyGoogleToken($idToken);

        if (!$googleUser) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid Google token. Please try again.',
            ], 401);
        }

        // ── Find or create user ───────────────────────────────────────────────
        $user = DB::transaction(function () use ($googleUser) {
            $existing = User::where('email', $googleUser['email'])->first();

            if ($existing) {
                // Update Google-specific fields if missing
                $updates = [];
                if (!$existing->google_id && isset($googleUser['sub'])) {
                    $updates['google_id'] = $googleUser['sub'];
                }
                if (!$existing->profile_photo_url && isset($googleUser['picture'])) {
                    $updates['profile_photo_url'] = $googleUser['picture'];
                }
                if ($updates) {
                    $existing->update($updates);
                }
                return $existing;
            }

            // New user — register via Google
            $user = User::create([
                'name'              => $googleUser['name'] ?? 'Google User',
                'email'             => $googleUser['email'],
                'google_id'         => $googleUser['sub'] ?? null,
                'profile_photo_url' => $googleUser['picture'] ?? null,
                'email_verified_at' => now(), // Google already verified the email
                'referral_code'     => strtoupper(Str::random(8)),
                'points'            => 0,
                'password'          => bcrypt(Str::random(32)), // random unusable password
                'role'              => 'customer',
            ]);

            AuditLogger::logAuth('google_register', $user->id, true);

            return $user;
        });

        AuditLogger::logAuth('google_login', $user->id, true);

        $token = $this->authService->createToken($user, $deviceName);

        return response()->json([
            'success'    => true,
            'message'    => 'Google sign-in successful',
            'token'      => $token,
            'is_new_user' => !$user->wasRecentlyCreated ? false : true,
            'user'       => $this->formatUserResponse($user),
        ]);
    }

    /**
     * Verify Google ID token using Google's tokeninfo endpoint.
     * Returns the payload array on success, null on failure.
     */
    private function verifyGoogleToken(string $idToken): ?array
    {
        try {
            // Use Google's tokeninfo endpoint to verify
            $response = Http::timeout(10)->get('https://oauth2.googleapis.com/tokeninfo', [
                'id_token' => $idToken,
            ]);

            if (!$response->successful()) {
                \Log::warning('Google token verification failed', [
                    'status' => $response->status(),
                    'body'   => $response->body(),
                ]);
                return null;
            }

            $payload = $response->json();

            // Validate the audience (aud) matches your Google Client ID
            $clientId = config('services.google.client_id');
            if ($clientId && isset($payload['aud']) && $payload['aud'] !== $clientId) {
                \Log::warning('Google token audience mismatch', [
                    'expected' => $clientId,
                    'got'      => $payload['aud'],
                ]);
                return null;
            }

            // Must have an email
            if (empty($payload['email'])) {
                return null;
            }

            return $payload;
        } catch (\Exception $e) {
            \Log::error('Google token verification error: ' . $e->getMessage());
            return null;
        }
    }

    private function formatUserResponse(User $user): array
    {
        return $user->only([
            'id', 'name', 'email', 'phone_number',
            'profile_photo_url', 'profile_picture',
            'referral_code', 'points', 'role',
            'email_verified_at', 'created_at',
        ]);
    }
}
