<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeviceController extends Controller
{
    /**
     * Register FCM device token (mobile alias for /auth/fcm-token).
     */
    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'token'     => 'nullable|string|max:512',
            'fcm_token' => 'nullable|string|max:512',
            'platform'  => 'nullable|string|max:32',
            'app'       => 'nullable|string|max:64',
        ]);

        $token = $validated['fcm_token'] ?? $validated['token'] ?? null;

        if (!$token) {
            return $this->error('FCM token is required.', 422);
        }

        $request->user()->update(['fcm_token' => $token]);

        return $this->success([
            'registered' => true,
            'platform'   => $validated['platform'] ?? null,
            'app'        => $validated['app'] ?? null,
        ], 'Device registered successfully');
    }
}
