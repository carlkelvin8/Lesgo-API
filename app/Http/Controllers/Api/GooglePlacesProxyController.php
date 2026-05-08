<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * @OA\Tag(
 *     name="Google Places Proxy",
 *     description="Proxy endpoints for Google Places API to avoid CORS issues"
 * )
 */
class GooglePlacesProxyController extends Controller
{
    private $apiKey;

    public function __construct()
    {
        // Use the Google Maps API key from environment
        $this->apiKey = 'AIzaSyBekzwaPQ9H0Zu3y7XOyUkrM-ny4XdVZXA';
    }

    /**
     * @OA\Get(
     *     path="/api/v1/google-places/autocomplete",
     *     summary="Google Places Autocomplete Proxy",
     *     tags={"Google Places Proxy"},
     *     @OA\Parameter(
     *         name="input",
     *         in="query",
     *         required=true,
     *         description="Search query",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Autocomplete suggestions"
     *     )
     * )
     */
    public function autocomplete(Request $request)
    {
        try {
            $input = $request->query('input');
            
            if (empty($input)) {
                return response()->json([
                    'status' => 'INVALID_REQUEST',
                    'predictions' => []
                ]);
            }

            // Call Google Places Autocomplete API
            $response = Http::get('https://maps.googleapis.com/maps/api/place/autocomplete/json', [
                'input' => $input,
                'key' => $this->apiKey,
                'components' => 'country:ph', // Restrict to Philippines
                'language' => 'en',
            ]);

            if ($response->successful()) {
                return response()->json($response->json());
            }

            return response()->json([
                'status' => 'ERROR',
                'predictions' => [],
                'error_message' => 'Failed to fetch autocomplete results'
            ], 500);

        } catch (\Exception $e) {
            Log::error('Google Places Autocomplete Error: ' . $e->getMessage());
            
            return response()->json([
                'status' => 'ERROR',
                'predictions' => [],
                'error_message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/v1/google-places/details",
     *     summary="Google Place Details Proxy",
     *     tags={"Google Places Proxy"},
     *     @OA\Parameter(
     *         name="place_id",
     *         in="query",
     *         required=true,
     *         description="Google Place ID",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Place details"
     *     )
     * )
     */
    public function details(Request $request)
    {
        try {
            $placeId = $request->query('place_id');
            
            if (empty($placeId)) {
                return response()->json([
                    'status' => 'INVALID_REQUEST',
                    'result' => null
                ]);
            }

            // Call Google Place Details API
            $response = Http::get('https://maps.googleapis.com/maps/api/place/details/json', [
                'place_id' => $placeId,
                'key' => $this->apiKey,
                'fields' => 'geometry,formatted_address,address_components',
            ]);

            if ($response->successful()) {
                return response()->json($response->json());
            }

            return response()->json([
                'status' => 'ERROR',
                'result' => null,
                'error_message' => 'Failed to fetch place details'
            ], 500);

        } catch (\Exception $e) {
            Log::error('Google Place Details Error: ' . $e->getMessage());
            
            return response()->json([
                'status' => 'ERROR',
                'result' => null,
                'error_message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/v1/google-places/directions",
     *     summary="Google Directions API Proxy",
     *     tags={"Google Places Proxy"},
     *     @OA\Parameter(
     *         name="origin",
     *         in="query",
     *         required=true,
     *         description="Origin coordinates (lat,lng)",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="destination",
     *         in="query",
     *         required=true,
     *         description="Destination coordinates (lat,lng)",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Route directions"
     *     )
     * )
     */
    public function directions(Request $request)
    {
        try {
            $origin = $request->query('origin');
            $destination = $request->query('destination');
            
            Log::info('Directions API called', [
                'origin' => $origin,
                'destination' => $destination
            ]);
            
            if (empty($origin) || empty($destination)) {
                return response()->json([
                    'status' => 'INVALID_REQUEST',
                    'routes' => []
                ]);
            }

            // Call Google Directions API
            $response = Http::get('https://maps.googleapis.com/maps/api/directions/json', [
                'origin' => $origin,
                'destination' => $destination,
                'key' => $this->apiKey,
                'mode' => 'driving', // Can be: driving, walking, bicycling, transit
                'alternatives' => false,
            ]);

            Log::info('Google Directions API response', [
                'status_code' => $response->status(),
                'body' => $response->json()
            ]);

            if ($response->successful()) {
                return response()->json($response->json());
            }

            return response()->json([
                'status' => 'ERROR',
                'routes' => [],
                'error_message' => 'Failed to fetch directions'
            ], 500);

        } catch (\Exception $e) {
            Log::error('Google Directions Error: ' . $e->getMessage());
            
            return response()->json([
                'status' => 'ERROR',
                'routes' => [],
                'error_message' => $e->getMessage()
            ], 500);
        }
    }
}
