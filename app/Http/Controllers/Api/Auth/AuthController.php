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
     * Register a new user.
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
     * Authenticate user and return token.
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
     * Get authenticated user details.
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
     * Update authenticated user profile.
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
     * Logout current session.
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
     * Logout from all devices.
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

