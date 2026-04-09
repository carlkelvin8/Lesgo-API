<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Geofence;
use App\Models\GeofenceEvent;
use App\Services\RealtimeService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\Rule;

class GeofenceController extends Controller
{
    protected GeofencingService $geofencingService;

    public function __construct(
        GeofencingService $geofencingService,
        RealtimeService $realtimeService
    ) {
        $this->geofencingService = $geofencingService;
        $this->realtimeService = $realtimeService;
    }

    /**
     * Get all geofences for the authenticated user.
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'type' => 'nullable|string',
            'is_active' => 'nullable|boolean',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $query = Geofence::with(['creator'])
            ->where('created_by', auth()->id())
            ->when($request->type, fn($q) => $q->where('type', $request->type))
            ->when($request->has('is_active'), fn($q) => $q->where('is_active', $request->is_active))
            ->orderBy('created_at', 'desc');

        $geofences = $query->paginate($request->per_page ?? 20);

        return response()->json([
            'success' => true,
            'message' => 'Geofences retrieved successfully',
            'request_id' => $request->header('X-Request-ID', uniqid()),
            'data' => $geofences->items(),
            'meta' => [
                'total' => $geofences->total(),
                'per_page' => $geofences->perPage(),
                'current_page' => $geofences->currentPage(),
                'last_page' => $geofences->lastPage(),
                'from' => $geofences->firstItem(),
                'to' => $geofences->lastItem(),
                'has_more' => $geofences->hasMorePages(),
            ],
        ]);
    }

    /**
     * Create a new geofence.
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'type' => [
                'required',
                'string',
                Rule::in(array_keys(Geofence::getGeofenceTypes()))
            ],
            'shape' => 'required|in:circle,polygon',
            'center_latitude' => 'required|numeric|between:-90,90',
            'center_longitude' => 'required|numeric|between:-180,180',
            'radius_meters' => 'required_if:shape,circle|nullable|integer|min:10|max:50000',
            'polygon_coordinates' => 'required_if:shape,polygon|nullable|array|min:3',
            'polygon_coordinates.*.latitude' => 'required_with:polygon_coordinates|numeric|between:-90,90',
            'polygon_coordinates.*.longitude' => 'required_with:polygon_coordinates|numeric|between:-180,180',
            'trigger_on_enter' => 'nullable|boolean',
            'trigger_on_exit' => 'nullable|boolean',
            'trigger_on_dwell' => 'nullable|boolean',
            'dwell_time_seconds' => 'nullable|integer|min:60|max:86400',
            'notification_types' => 'nullable|array',
            'notification_types.*' => Rule::in(array_keys(Geofence::getNotificationTypes())),
            'notification_recipients' => 'nullable|array',
            'notification_recipients.*' => 'integer|exists:users,id',
            'enter_message' => 'nullable|string|max:500',
            'exit_message' => 'nullable|string|max:500',
            'dwell_message' => 'nullable|string|max:500',
            'active_days' => 'nullable|array',
            'active_days.*' => 'string|in:monday,tuesday,wednesday,thursday,friday,saturday,sunday',
            'active_start_time' => 'nullable|date_format:H:i',
            'active_end_time' => 'nullable|date_format:H:i',
            'timezone' => 'nullable|string|max:50',
            'priority' => 'nullable|integer|min:1|max:4',
            'metadata' => 'nullable|array',
        ]);

        $geofence = Geofence::create([
            'created_by' => auth()->id(),
            'name' => $request->name,
            'description' => $request->description,
            'type' => $request->type,
            'shape' => $request->shape,
            'center_latitude' => $request->center_latitude,
            'center_longitude' => $request->center_longitude,
            'radius_meters' => $request->radius_meters,
            'polygon_coordinates' => $request->polygon_coordinates,
            'trigger_on_enter' => $request->trigger_on_enter ?? true,
            'trigger_on_exit' => $request->trigger_on_exit ?? true,
            'trigger_on_dwell' => $request->trigger_on_dwell ?? false,
            'dwell_time_seconds' => $request->dwell_time_seconds,
            'notification_types' => $request->notification_types ?? ['push'],
            'notification_recipients' => $request->notification_recipients ?? [auth()->id()],
            'enter_message' => $request->enter_message,
            'exit_message' => $request->exit_message,
            'dwell_message' => $request->dwell_message,
            'active_days' => $request->active_days,
            'active_start_time' => $request->active_start_time,
            'active_end_time' => $request->active_end_time,
            'timezone' => $request->timezone ?? 'Asia/Manila',
            'priority' => $request->priority ?? 1,
            'metadata' => $request->metadata ?? [],
        ]);

        $geofence->load(['creator']);

        return response()->json([
            'success' => true,
            'message' => 'Geofence created successfully',
            'request_id' => $request->header('X-Request-ID', uniqid()),
            'data' => $geofence,
        ], 201);
    }

    /**
     * Get a specific geofence.
     */
    public function show(Geofence $geofence): JsonResponse
    {
        // Check if user owns the geofence
        if ($geofence->created_by !== auth()->id()) {
            return response()->json([
                'success' => false,
                'message' => 'You can only view your own geofences',
                'request_id' => request()->header('X-Request-ID', uniqid()),
            ], 403);
        }

        $geofence->load(['creator', 'recentEvents' => function ($query) {
            $query->with('user')->limit(10);
        }]);

        return response()->json([
            'success' => true,
            'message' => 'Geofence retrieved successfully',
            'request_id' => request()->header('X-Request-ID', uniqid()),
            'data' => $geofence,
        ]);
    }

    /**
     * Update a geofence.
     */
    public function update(Request $request, Geofence $geofence): JsonResponse
    {
        // Check if user owns the geofence
        if ($geofence->created_by !== auth()->id()) {
            return response()->json([
                'success' => false,
                'message' => 'You can only update your own geofences',
                'request_id' => $request->header('X-Request-ID', uniqid()),
            ], 403);
        }

        $request->validate([
            'name' => 'nullable|string|max:255',
            'description' => 'nullable|string|max:1000',
            'type' => [
                'nullable',
                'string',
                Rule::in(array_keys(Geofence::getGeofenceTypes()))
            ],
            'radius_meters' => 'nullable|integer|min:10|max:50000',
            'trigger_on_enter' => 'nullable|boolean',
            'trigger_on_exit' => 'nullable|boolean',
            'trigger_on_dwell' => 'nullable|boolean',
            'dwell_time_seconds' => 'nullable|integer|min:60|max:86400',
            'notification_types' => 'nullable|array',
            'notification_types.*' => Rule::in(array_keys(Geofence::getNotificationTypes())),
            'notification_recipients' => 'nullable|array',
            'notification_recipients.*' => 'integer|exists:users,id',
            'enter_message' => 'nullable|string|max:500',
            'exit_message' => 'nullable|string|max:500',
            'dwell_message' => 'nullable|string|max:500',
            'active_days' => 'nullable|array',
            'active_days.*' => 'string|in:monday,tuesday,wednesday,thursday,friday,saturday,sunday',
            'active_start_time' => 'nullable|date_format:H:i',
            'active_end_time' => 'nullable|date_format:H:i',
            'timezone' => 'nullable|string|max:50',
            'is_active' => 'nullable|boolean',
            'priority' => 'nullable|integer|min:1|max:4',
            'metadata' => 'nullable|array',
        ]);

        $geofence->update($request->only([
            'name', 'description', 'type', 'radius_meters',
            'trigger_on_enter', 'trigger_on_exit', 'trigger_on_dwell', 'dwell_time_seconds',
            'notification_types', 'notification_recipients',
            'enter_message', 'exit_message', 'dwell_message',
            'active_days', 'active_start_time', 'active_end_time', 'timezone',
            'is_active', 'priority', 'metadata'
        ]));

        $geofence->load(['creator']);

        return response()->json([
            'success' => true,
            'message' => 'Geofence updated successfully',
            'request_id' => $request->header('X-Request-ID', uniqid()),
            'data' => $geofence,
        ]);
    }

    /**
     * Delete a geofence.
     */
    public function destroy(Geofence $geofence): JsonResponse
    {
        // Check if user owns the geofence
        if ($geofence->created_by !== auth()->id()) {
            return response()->json([
                'success' => false,
                'message' => 'You can only delete your own geofences',
                'request_id' => request()->header('X-Request-ID', uniqid()),
            ], 403);
        }

        $geofence->delete();

        return response()->json([
            'success' => true,
            'message' => 'Geofence deleted successfully',
            'request_id' => request()->header('X-Request-ID', uniqid()),
        ]);
    }

    /**
     * Process location update and trigger geofence events.
     */
    public function processLocation(Request $request): JsonResponse
    {
        $request->validate([
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'accuracy_meters' => 'nullable|numeric|min:0',
            'speed_kmh' => 'nullable|numeric|min:0',
            'bearing_degrees' => 'nullable|numeric|between:0,360',
            'address' => 'nullable|string|max:255',
            'device_id' => 'nullable|string|max:100',
            'device_type' => 'nullable|string|in:ios,android,web',
            'order_id' => 'nullable|exists:orders,id',
            'session_id' => 'nullable|string|max:100',
            'metadata' => 'nullable|array',
        ]);

        $locationData = $request->only([
            'accuracy_meters', 'speed_kmh', 'bearing_degrees', 'address',
            'device_id', 'device_type', 'order_id', 'session_id', 'metadata'
        ]);

        $triggeredEvents = $this->geofencingService->processLocationUpdate(
            auth()->user(),
            $request->latitude,
            $request->longitude,
            $locationData
        );

        return response()->json([
            'success' => true,
            'message' => 'Location processed successfully',
            'request_id' => $request->header('X-Request-ID', uniqid()),
            'data' => [
                'triggered_events' => $triggeredEvents,
                'events_count' => count($triggeredEvents),
                'location' => [
                    'latitude' => $request->latitude,
                    'longitude' => $request->longitude,
                ],
            ],
        ]);
    }

    /**
     * Get geofence events for the authenticated user.
     */
    public function events(Request $request): JsonResponse
    {
        $request->validate([
            'geofence_id' => 'nullable|exists:geofences,id',
            'event_type' => 'nullable|in:enter,exit,dwell',
            'days' => 'nullable|integer|min:1|max:90',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $days = $request->days ?? 7;

        $query = GeofenceEvent::with(['geofence', 'order'])
            ->where('user_id', auth()->id())
            ->where('event_time', '>=', now()->subDays($days))
            ->when($request->geofence_id, fn($q) => $q->where('geofence_id', $request->geofence_id))
            ->when($request->event_type, fn($q) => $q->where('event_type', $request->event_type))
            ->orderBy('event_time', 'desc');

        $events = $query->paginate($request->per_page ?? 20);

        return response()->json([
            'success' => true,
            'message' => 'Geofence events retrieved successfully',
            'request_id' => $request->header('X-Request-ID', uniqid()),
            'data' => $events->items(),
            'meta' => [
                'total' => $events->total(),
                'per_page' => $events->perPage(),
                'current_page' => $events->currentPage(),
                'last_page' => $events->lastPage(),
                'from' => $events->firstItem(),
                'to' => $events->lastItem(),
                'has_more' => $events->hasMorePages(),
                'period_days' => $days,
            ],
        ]);
    }

    /**
     * Get geofence analytics.
     */
    public function analytics(Request $request, Geofence $geofence): JsonResponse
    {
        // Check if user owns the geofence
        if ($geofence->created_by !== auth()->id()) {
            return response()->json([
                'success' => false,
                'message' => 'You can only view analytics for your own geofences',
                'request_id' => $request->header('X-Request-ID', uniqid()),
            ], 403);
        }

        $request->validate([
            'days' => 'nullable|integer|min:1|max:90',
        ]);

        $days = $request->days ?? 30;
        $analytics = $this->geofencingService->getGeofenceAnalytics($geofence, $days);

        return response()->json([
            'success' => true,
            'message' => 'Geofence analytics retrieved successfully',
            'request_id' => $request->header('X-Request-ID', uniqid()),
            'data' => $analytics,
            'meta' => [
                'period_days' => $days,
                'generated_at' => now()->toISOString(),
            ],
        ]);
    }

    /**
     * Get nearby geofences for a location.
     */
    public function nearby(Request $request): JsonResponse
    {
        $request->validate([
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'radius_km' => 'nullable|integer|min:1|max:100',
        ]);

        $radiusKm = $request->radius_km ?? 10;

        $geofences = Geofence::active()
            ->withinRadius($request->latitude, $request->longitude, $radiusKm)
            ->with(['creator'])
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Nearby geofences retrieved successfully',
            'request_id' => $request->header('X-Request-ID', uniqid()),
            'data' => $geofences,
            'meta' => [
                'search_location' => [
                    'latitude' => $request->latitude,
                    'longitude' => $request->longitude,
                ],
                'search_radius_km' => $radiusKm,
                'results_count' => $geofences->count(),
            ],
        ]);
    }

    /**
     * Get geofence configuration options.
     */
    public function config(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Geofence configuration retrieved successfully',
            'request_id' => request()->header('X-Request-ID', uniqid()),
            'data' => [
                'geofence_types' => Geofence::getGeofenceTypes(),
                'priority_levels' => Geofence::getPriorityLevels(),
                'notification_types' => Geofence::getNotificationTypes(),
                'event_types' => GeofenceEvent::getEventTypes(),
                'shapes' => ['circle', 'polygon'],
                'limits' => [
                    'min_radius_meters' => 10,
                    'max_radius_meters' => 50000,
                    'min_dwell_time_seconds' => 60,
                    'max_dwell_time_seconds' => 86400,
                    'max_polygon_vertices' => 100,
                ],
                'timezones' => [
                    'Asia/Manila' => 'Philippines (Manila)',
                    'UTC' => 'UTC',
                ],
            ],
        ]);
    }

    /**
     * Test if a point is inside a geofence.
     */
    public function testPoint(Request $request, Geofence $geofence): JsonResponse
    {
        // Check if user owns the geofence
        if ($geofence->created_by !== auth()->id()) {
            return response()->json([
                'success' => false,
                'message' => 'You can only test your own geofences',
                'request_id' => $request->header('X-Request-ID', uniqid()),
            ], 403);
        }

        $request->validate([
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
        ]);

        $isInside = $geofence->containsPoint($request->latitude, $request->longitude);
        $distance = $geofence->getDistanceFromPoint($request->latitude, $request->longitude);

        return response()->json([
            'success' => true,
            'message' => 'Point test completed successfully',
            'request_id' => $request->header('X-Request-ID', uniqid()),
            'data' => [
                'is_inside' => $isInside,
                'distance_meters' => round($distance, 2),
                'geofence' => [
                    'id' => $geofence->id,
                    'name' => $geofence->name,
                    'shape' => $geofence->shape,
                    'center' => [
                        'latitude' => $geofence->center_latitude,
                        'longitude' => $geofence->center_longitude,
                    ],
                    'radius_meters' => $geofence->radius_meters,
                ],
                'test_point' => [
                    'latitude' => $request->latitude,
                    'longitude' => $request->longitude,
                ],
            ],
        ]);
    }
}