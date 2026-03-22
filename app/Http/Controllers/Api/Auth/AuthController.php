<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Http\Requests\UpdateProfileRequest;
use App\Models\User;
use App\Services\AuthenticationService;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function __construct(
        private AuthenticationService $authService
    ) {}

    /**
     * @OA\Post(
     *     path="/api/v1/auth/register",
     *     summary="Register a new user",
     *     tags={"Auth"},
     *     @OA\RequestBody(required=true,
     *         @OA\JsonContent(required={"name","email","password","role"},
     *             @OA\Property(property="name", type="string", example="Juan dela Cruz"),
     *             @OA\Property(property="email", type="string", format="email", example="juan@example.com"),
     *             @OA\Property(property="phone_number", type="string", example="+639171234567"),
     *             @OA\Property(property="password", type="string", format="password", example="secret123"),
     *             @OA\Property(property="password_confirmation", type="string", example="secret123"),
     *             @OA\Property(property="role", type="string", enum={"customer","driver","partner_admin"}, example="customer"),
     *             @OA\Property(property="device_name", type="string", example="mobile-app")
     *         )
     *     ),
     *     @OA\Response(response=201, description="Registration successful",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="token", type="string"),
     *             @OA\Property(property="user", ref="#/components/schemas/User")
     *         )
     *     ),
     *     @OA\Response(response=422, ref="#/components/schemas/ErrorResponse")
     * )
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $user = DB::transaction(function () use ($validated) {
            $user = User::create([
                'name'         => $validated['name'],
                'email'        => $validated['email'],
                'phone_number' => $validated['phone_number'] ?? null,
                'password'     => Hash::make($validated['password']),
                'role'         => $validated['role'],
            ]);

            AuditLogger::logAuth('register', $user->id, true);

            return $user;
        });

        $deviceName = $request->input('device_name', 'api-token');
        $token = $this->authService->createToken($user, $deviceName);

        return response()->json([
            'success' => true,
            'message' => 'Registration successful',
            'token'   => $token,
            'user'    => $this->formatUserResponse($user),
        ], 201);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/auth/login",
     *     summary="Login and get token",
     *     tags={"Auth"},
     *     @OA\RequestBody(required=true,
     *         @OA\JsonContent(required={"email","password"},
     *             @OA\Property(property="email", type="string", format="email", example="juan@example.com"),
     *             @OA\Property(property="password", type="string", format="password", example="secret123"),
     *             @OA\Property(property="device_name", type="string", example="mobile-app"),
     *             @OA\Property(property="revoke_others", type="boolean", example=false)
     *         )
     *     ),
     *     @OA\Response(response=200, description="Login successful",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="token", type="string"),
     *             @OA\Property(property="user", ref="#/components/schemas/User")
     *         )
     *     ),
     *     @OA\Response(response=401, ref="#/components/schemas/ErrorResponse"),
     *     @OA\Response(response=429, description="Too many attempts")
     * )
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $user = $this->authService->authenticate(
            $validated['email'],
            $validated['password'],
            $request->ip()
        );

        if ($request->boolean('revoke_others', false)) {
            $this->authService->revokeAllTokens($user);
        }

        $deviceName = $request->input('device_name', 'api-token');
        $token = $this->authService->createToken($user, $deviceName);

        return response()->json([
            'success' => true,
            'message' => 'Login successful',
            'token'   => $token,
            'user'    => $this->formatUserResponse($user),
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/auth/me",
     *     summary="Get authenticated user",
     *     tags={"Auth"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(response=200, description="Current user",
     *         @OA\JsonContent(@OA\Property(property="user", ref="#/components/schemas/User"))
     *     ),
     *     @OA\Response(response=401, ref="#/components/schemas/ErrorResponse")
     * )
     */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'success' => true,
            'user'    => $this->formatUserResponse($user),
        ]);
    }

    /**
     * @OA\Put(
     *     path="/api/v1/auth/me",
     *     summary="Update authenticated user profile",
     *     tags={"Auth"},
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="name", type="string"),
     *             @OA\Property(property="phone_number", type="string"),
     *             @OA\Property(property="current_password", type="string"),
     *             @OA\Property(property="password", type="string"),
     *             @OA\Property(property="password_confirmation", type="string")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Profile updated",
     *         @OA\JsonContent(@OA\Property(property="user", ref="#/components/schemas/User"))
     *     ),
     *     @OA\Response(response=422, ref="#/components/schemas/ErrorResponse")
     * )
     */
    public function updateProfile(UpdateProfileRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $user = $request->user();

        // Verify current password if changing password
        if (isset($validated['password'])) {
            if (!Hash::check($validated['current_password'], $user->password)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Current password is incorrect',
                ], 422);
            }

            $validated['password'] = Hash::make($validated['password']);
            
            // Revoke all tokens on password change
            $this->authService->revokeAllTokens($user);
        }

        $oldData = $user->only(array_keys($validated));
        
        $user->update($validated);

        AuditLogger::logModification(
            'update',
            'User',
            $user->id,
            $oldData,
            $user->only(array_keys($validated)),
            $user->id
        );

        // Create new token if password was changed
        $newToken = null;
        if (isset($validated['password'])) {
            $newToken = $this->authService->createToken($user, 'api-token');
        }

        return response()->json([
            'success' => true,
            'message' => 'Profile updated successfully',
            'user'    => $this->formatUserResponse($user->fresh()),
            'token'   => $newToken,
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/auth/logout",
     *     summary="Logout current session",
     *     tags={"Auth"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(response=200, description="Logged out successfully")
     * )
     */
    public function logout(Request $request): JsonResponse
    {
        $this->authService->revokeCurrentToken($request->user());

        return response()->json([
            'success' => true,
            'message' => 'Logged out successfully',
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/auth/logout-all",
     *     summary="Logout from all devices",
     *     tags={"Auth"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(response=200, description="Logged out from all devices")
     * )
     */
    public function logoutAll(Request $request): JsonResponse
    {
        $this->authService->revokeAllTokens($request->user());

        return response()->json([
            'success' => true,
            'message' => 'Logged out from all devices',
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/auth/fcm-token",
     *     summary="Register or update FCM push token",
     *     tags={"Auth"},
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(required=true,
     *         @OA\JsonContent(required={"fcm_token"},
     *             @OA\Property(property="fcm_token", type="string", example="dGhpcyBpcyBhIHRlc3Q...")
     *         )
     *     ),
     *     @OA\Response(response=200, description="FCM token registered")
     * )
     */
    public function registerFcmToken(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'fcm_token' => 'required|string|max:512',
        ]);

        $request->user()->update(['fcm_token' => $validated['fcm_token']]);

        return response()->json(['success' => true, 'message' => 'FCM token registered']);
    }

    /**
     * Format user data for API response.
     */
    private function formatUserResponse(?User $user): ?array
    {
        if (!$user) {
            return null;
        }

        return $user->only([
            'id',
            'name',
            'email',
            'phone_number',
            'role',
            'email_verified_at',
            'created_at',
            'updated_at',
        ]);
    }
}

