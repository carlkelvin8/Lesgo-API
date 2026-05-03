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
                'name'          => $validated['name'],
                'email'         => $validated['email'],
                'phone_number'  => $validated['phone'] ?? null,
                'address_line1' => $validated['address_line_1'] ?? null,
                'address_line2' => $validated['address_line_2'] ?? null,
                'referral_code' => strtoupper(\Illuminate\Support\Str::random(8)),
                'points'        => 0,
                'password'      => Hash::make($validated['password']),
                'role'          => $validated['role'],
            ]);

            // Create Partner record for partner_admin registrations
            if ($validated['role'] === 'partner_admin') {
                $partnerName = $validated['restaurant_name'] ?? $validated['name'];
                
                // Generate unique slug from partner name
                $baseSlug = \Illuminate\Support\Str::slug($partnerName);
                $slug = $baseSlug;
                $counter = 1;
                
                // Ensure slug is unique
                while (\App\Models\Partner::where('slug', $slug)->exists()) {
                    $slug = $baseSlug . '-' . $counter;
                    $counter++;
                }
                
                \App\Models\Partner::create([
                    'user_id'          => $user->id,
                    'name'             => $partnerName,
                    'slug'             => $slug,
                    'status'           => 'pending', // Requires admin approval
                    'is_open'          => false,
                    'is_featured'      => false,
                    'accepts_online_payment' => false,
                    'documents'        => [
                        'selfie_path'            => $validated['selfie_path'] ?? null,
                        'valid_id_path'          => $validated['valid_id_path'] ?? null,
                        'digital_signature_path' => $validated['digital_signature_path'] ?? null,
                        'barangay_permit_path'   => $validated['barangay_permit_path'] ?? null,
                        'mayors_permit_path'     => $validated['mayors_permit_path'] ?? null,
                        'dti_permit_path'        => $validated['dti_permit_path'] ?? null,
                        'zip_code'               => $validated['zip_code'] ?? null,
                    ],
                ]);
            }

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

        // Load driver profile if user is a driver
        if ($user->isDriver()) {
            $user->load('driverProfile');
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
        
        // Load driver profile if user is a driver
        if ($user->isDriver()) {
            $user->load('driverProfile');
        }

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

        // Handle base64 profile photo
        if (isset($validated['profile_photo_url']) && str_starts_with($validated['profile_photo_url'], 'data:image')) {
            try {
                // Delete old profile picture if exists
                if ($user->profile_photo_url && \Storage::disk('public')->exists($user->profile_photo_url)) {
                    \Storage::disk('public')->delete($user->profile_photo_url);
                }

                // Extract base64 data
                $image = $validated['profile_photo_url'];
                $image = str_replace('data:image/png;base64,', '', $image);
                $image = str_replace('data:image/jpg;base64,', '', $image);
                $image = str_replace('data:image/jpeg;base64,', '', $image);
                $image = str_replace(' ', '+', $image);
                $imageData = base64_decode($image);

                // Generate unique filename
                $filename = 'profile_pictures/' . $user->id . '_' . time() . '.jpg';
                
                // Store the image
                \Storage::disk('public')->put($filename, $imageData);
                
                // Update the validated data with the file path
                $validated['profile_photo_url'] = $filename;
            } catch (\Exception $e) {
                \Log::error('Failed to process profile photo: ' . $e->getMessage());
                // Remove profile_photo_url from validated data if processing failed
                unset($validated['profile_photo_url']);
            }
        }

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
     *     path="/api/v1/auth/me/profile-picture",
     *     summary="Upload profile picture",
     *     tags={"Auth"},
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 @OA\Property(property="profile_picture", type="string", format="binary")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=200, description="Profile picture uploaded successfully")
     * )
     */
    public function uploadProfilePicture(Request $request): JsonResponse
    {
        $request->validate([
            'profile_picture' => 'required|image|mimes:jpeg,png,jpg,gif|max:5120', // 5MB max
        ]);

        $user = $request->user();

        try {
            // Delete old profile picture if exists
            if ($user->profile_picture && \Storage::disk('public')->exists($user->profile_picture)) {
                \Storage::disk('public')->delete($user->profile_picture);
            }

            // Store new profile picture
            $file = $request->file('profile_picture');
            $path = $file->store('profile_pictures', 'public');

            // Update user record
            $user->update(['profile_picture' => $path]);

            return response()->json([
                'success' => true,
                'message' => 'Profile picture uploaded successfully',
                'profile_picture_url' => asset('storage/' . $path),
                'user' => $this->formatUserResponse($user->fresh()),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to upload profile picture: ' . $e->getMessage(),
            ], 500);
        }
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

        $userData = $user->only([
            'id',
            'name',
            'email',
            'phone_number',
            'date_of_birth',
            'address_line1',
            'address_line2',
            'profile_photo_url',
            'profile_picture',
            'referral_code',
            'referred_by',
            'points',
            'role',
            'email_verified_at',
            'created_at',
            'updated_at',
        ]);

        \Log::info('formatUserResponse - Original profile_photo_url: ' . ($userData['profile_photo_url'] ?? 'null'));

        // Convert profile_photo_url to full URL if it's a file path
        if (!empty($userData['profile_photo_url']) && !str_starts_with($userData['profile_photo_url'], 'http')) {
            // Use API route instead of direct storage link to ensure CORS headers
            $userData['profile_photo_url'] = url('/api/v1/storage/' . $userData['profile_photo_url']);
            \Log::info('formatUserResponse - Converted to: ' . $userData['profile_photo_url']);
        } else {
            \Log::info('formatUserResponse - No conversion needed (empty or already http)');
        }

        // Include driver_profile if exists
        if ($user->driverProfile) {
            $userData['driver_profile'] = $user->driverProfile->only([
                'id',
                'vehicle_type',
                'plate_number',
                'license_number',
                'status',
                'last_latitude',
                'last_longitude',
            ]);
        }

        return $userData;
    }
}

