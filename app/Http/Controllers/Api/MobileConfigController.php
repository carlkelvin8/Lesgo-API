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
                    'ios_bundle_id' => $firebase['ios_bundle_id'] ?? 'com.lesgo.rider',
                ],
                'rider_app' => [
                    'min_build' => (int) config('services.mobile_app.rider_min_build', 1),
                    'store_url' => config('services.mobile_app.rider_store_url'),
                    'message' => config('services.mobile_app.rider_update_message'),
                ],
                'merchant_app' => [
                    'min_build' => (int) config('services.mobile_app.merchant_min_build', 1),
                    'store_url' => config('services.mobile_app.merchant_store_url'),
                    'message' => config('services.mobile_app.merchant_update_message'),
                ],
                'safety' => [
                    'support_phone' => config('services.support.phone', '+639171234567'),
                    'emergency_number' => config('services.support.emergency', '911'),
                    'support_email' => config('services.support.email', 'support@lesgo.com'),
                ],
                'support_categories' => [
                    'general',
                    'payments',
                    'orders',
                    'account',
                    'technical',
                    'safety',
                ],
            ],
        ]);
    }
}
