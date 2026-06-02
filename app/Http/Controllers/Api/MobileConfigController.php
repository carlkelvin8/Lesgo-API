<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

/**
 * Public mobile app configuration (non-secret client IDs / Firebase client config).
 */
class MobileConfigController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $firebase = config('services.firebase', []);

        return response()->json([
            'success' => true,
            'data' => [
                'google_web_client_id' => config('services.google.client_id'),
                'google_maps_api_key' => config('services.google_maps.api_key'),
                'firebase' => [
                    'project_id' => $firebase['project_id'] ?? null,
                    'messaging_sender_id' => $firebase['messaging_sender_id'] ?? null,
                    'android_api_key' => $firebase['android_api_key'] ?? null,
                    'android_app_id' => $firebase['android_app_id'] ?? null,
                    'ios_api_key' => $firebase['ios_api_key'] ?? null,
                    'ios_app_id' => $firebase['ios_app_id'] ?? null,
                    'ios_bundle_id' => $firebase['ios_bundle_id'] ?? 'com.lesgo.app',
                ],
            ],
        ]);
    }
}
