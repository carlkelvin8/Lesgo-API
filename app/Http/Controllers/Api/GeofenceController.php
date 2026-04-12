<?php

namespace App\Http\Controllers\Api;

use App\Models\Geofence;
use App\Models\GeofenceEvent;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class GeofenceController extends Controller
{
    /**
     * List geofences with filtering.
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type' => 'nullable|in:service_area,restricted_zone,high_demand_zone',
            'is_active' => 'nullable|boolean',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $user = $request->user();
        $query = Geofence::query();

        // Only admins can manage geofences
        if (!$user->isAdmin()) {
            $query->where('is_active', true);
        }

        if (!empty($validated['type'])) {
            $query->where('type', $validated['type']);
        }

        if (isset($validated['is_active'])) {
            $query->where('is_active', $validated['is_active']);
        }

        $perPage = (int) ($validated['per_page'] ?? 20);
        $geofences = $query->orderBy('name')->paginate($perPage);

        return $this->success($geofences, 'Geofences retrieved successfully');
    }

    /**
     * Create a new geofence.
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        
        if (!$user->isAdmin()) {
            return $this->error('Forbidden', 403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|in:service_area,restricted_zone,high_demand_zone',
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'radius_km' => 'required|numeric|min:0.1|max:100',
            'is_active' => 'nullable|boolean',
            'metadata' => 'nullable|array',
        ]);

        $validated['is_active'] = $validated['is_active'] ?? true;

        $geofence = Geofence::create($validated);

        return $this->created($geofence, 'Geofence created successfully');
    }

    /**
     * Get geofence types.
     */
    public function types(): JsonResponse
    {
        return $this->success([
            [
                'type' => 'service_area',
                'label' => 'Service Area',
                'description' => 'Area where services are available',
                'color' => '#4CAF50',
            ],
            [
                'type' => 'restricted_zone',
                'label' => 'Restricted Zone',
                'description' => 'Area where services are restricted',
                'color' => '#F44336',
            ],
            [
                'type' => 'high_demand_zone',
                'label' => 'High Demand Zone',
                'description' => 'Area with high service demand',
                'color' => '#FF9800',
            ],
        ], 'Geofence types retrieved successfully');
    }

    /**
     * Get nearby geofences for a location.
     */
    public function nearby(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'lat' => 'required|numeric|between:-90,90',
            'lng' => 'required|numeric|between:-180,180',
            'radius_km' => 'nullable|numeric|min:1|max:50',
        ]);

        $lat = $validated['lat'];
        $lng = $validated['lng'];
        $radius = $validated['radius_km'] ?? 10;

        // Haversine formula to find nearby geofences
        $geofences = Geofence::where('is_active', true)
            ->selectRaw("*, 
                (6371 * acos(cos(radians(?)) * cos(radians(latitude)) * cos(radians(longitude) - radians(?)) + sin(radians(?)) * sin(radians(latitude)))) AS distance_km", 
                [$lat, $lng, $lat])
            ->having('distance_km', '<=', $radius + DB::raw('radius_km'))
            ->orderBy('distance_km')
            ->get();

        return $this->success($geofences, 'Nearby geofences retrieved successfully');
    }

    /**
     * Get geofence statistics.
     */
    public function statistics(Request $request): JsonResponse
    {
        $user = $request->user();
        
        // Allow all authenticated users to view statistics
        // Only restrict management endpoints to admins

        $totalGeofences = \App\Models\Geofence::count();
        $activeGeofences = \App\Models\Geofence::where('is_active', true)->count();
        $totalEvents = \App\Models\GeofenceEvent::count();
        $eventsToday = \App\Models\GeofenceEvent::whereDate('created_at', today())->count();

        return $this->success([
            'total_geofences' => $totalGeofences,
            'active_geofences' => $activeGeofences,
            'inactive_geofences' => $totalGeofences - $activeGeofences,
            'total_events' => $totalEvents,
            'events_today' => $eventsToday,
        ], 'Geofence statistics retrieved successfully');
    }

    /**
     * Get a specific geofence.
     */
    public function show($geofenceId): JsonResponse
    {
        $geofence = Geofence::find($geofenceId);

        if (!$geofence) {
            return $this->error('Geofence not found.', 404);
        }

        return $this->success($geofence, 'Geofence retrieved successfully');
    }

    /**
     * Update a geofence.
     */
    public function update(Request $request, $geofenceId): JsonResponse
    {
        $user = $request->user();
        
        if (!$user->isAdmin()) {
            return $this->error('Forbidden', 403);
        }

        $geofence = Geofence::find($geofenceId);

        if (!$geofence) {
            return $this->error('Geofence not found.', 404);
        }

        $validated = $request->validate([
            'name' => 'nullable|string|max:255',
            'type' => 'nullable|in:service_area,restricted_zone,high_demand_zone',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'radius_km' => 'nullable|numeric|min:0.1|max:100',
            'is_active' => 'nullable|boolean',
            'metadata' => 'nullable|array',
        ]);

        $geofence->update($validated);

        return $this->success($geofence, 'Geofence updated successfully');
    }

    /**
     * Delete a geofence.
     */
    public function destroy(Request $request, $geofenceId): JsonResponse
    {
        $user = $request->user();
        
        if (!$user->isAdmin()) {
            return $this->error('Forbidden', 403);
        }

        $geofence = Geofence::find($geofenceId);

        if (!$geofence) {
            return $this->error('Geofence not found.', 404);
        }

        $geofence->delete();

        return $this->success(['id' => $geofenceId], 'Geofence deleted successfully');
    }

    /**
     * Toggle geofence active status.
     */
    public function toggle(Request $request, $geofenceId): JsonResponse
    {
        $user = $request->user();
        
        if (!$user->isAdmin()) {
            return $this->error('Forbidden', 403);
        }

        $geofence = Geofence::find($geofenceId);

        if (!$geofence) {
            return $this->error('Geofence not found.', 404);
        }

        $geofence->update(['is_active' => !$geofence->is_active]);

        return $this->success([
            'id' => $geofence->id,
            'is_active' => $geofence->is_active,
        ], 'Geofence toggled successfully');
    }

    /**
     * Get events for a geofence.
     */
    public function events(Request $request, $geofenceId): JsonResponse
    {
        $geofence = Geofence::find($geofenceId);

        if (!$geofence) {
            return $this->error('Geofence not found.', 404);
        }

        $validated = $request->validate([
            'event_type' => 'nullable|in:enter,exit',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $query = $geofence->events()->with('user:id,name,email');

        if (!empty($validated['event_type'])) {
            $query->where('event_type', $validated['event_type']);
        }

        $perPage = (int) ($validated['per_page'] ?? 20);
        $events = $query->orderByDesc('created_at')->paginate($perPage);

        return $this->success($events, 'Geofence events retrieved successfully');
    }

    /**
     * Check if a location is inside any geofence.
     */
    public function checkLocation(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
        ]);

        $lat = $validated['latitude'];
        $lng = $validated['longitude'];

        // Find geofences containing this point
        $geofences = Geofence::where('is_active', true)
            ->get()
            ->filter(function ($geofence) use ($lat, $lng) {
                $distance = $this->calculateDistance($lat, $lng, $geofence->latitude, $geofence->longitude);
                return $distance <= $geofence->radius_km;
            });

        return $this->success([
            'inside_geofence' => $geofences->isNotEmpty(),
            'geofences' => $geofences->map(function ($g) {
                return [
                    'id' => $g->id,
                    'name' => $g->name,
                    'type' => $g->type,
                    'radius_km' => $g->radius_km,
                ];
            }),
        ], 'Location checked successfully');
    }

    /**
     * Process a location event (enter/exit geofence).
     */
    public function processLocation(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'event_type' => 'required|in:enter,exit',
        ]);

        $user = $request->user();
        $lat = $validated['latitude'];
        $lng = $validated['longitude'];
        $eventType = $validated['event_type'];

        // Find the closest geofence
        $closestGeofence = Geofence::where('is_active', true)
            ->get()
            ->sortBy(function ($geofence) use ($lat, $lng) {
                return $this->calculateDistance($lat, $lng, $geofence->latitude, $geofence->longitude);
            })
            ->first();

        if (!$closestGeofence) {
            return $this->error('No active geofences found.', 404);
        }

        // Create event
        $event = GeofenceEvent::create([
            'geofence_id' => $closestGeofence->id,
            'user_id' => $user->id,
            'event_type' => $eventType,
            'latitude' => $lat,
            'longitude' => $lng,
            'metadata' => [
                'geofence_name' => $closestGeofence->name,
                'geofence_type' => $closestGeofence->type,
            ],
        ]);

        return $this->created($event, 'Location processed successfully');
    }

    /**
     * Calculate distance between two points using Haversine formula.
     */
    private function calculateDistance($lat1, $lng1, $lat2, $lng2): float
    {
        $earthRadius = 6371; // km

        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);

        $a = sin($dLat / 2) * sin($dLat / 2) +
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
             sin($dLng / 2) * sin($dLng / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }
}
