<?php

namespace App\Services;

use App\Models\Geofence;
use App\Models\GeofenceEvent;
use App\Models\User;
use App\Models\Order;
use App\Services\NotificationService;
use App\Services\RealtimeService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class GeofencingService
{
    protected NotificationService $notificationService;

    public function __construct(
        NotificationService $notificationService,
        RealtimeService $realtimeService
    ) {
        $this->notificationService = $notificationService;
        $this->realtimeService = $realtimeService;
    }

    /**
     * Process location update and check for geofence events.
     */
    public function processLocationUpdate(
        User $user,
        float $latitude,
        float $longitude,
        array $locationData = []
    ): array {
        $triggeredEvents = [];
        
        // Find all active geofences that contain this location
        $activeGeofences = Geofence::findGeofencesForLocation($latitude, $longitude);
        
        // Get user's recent geofence states (last 30 minutes)
        $recentEvents = GeofenceEvent::where('user_id', $user->id)
            ->where('event_time', '>=', now()->subMinutes(30))
            ->orderBy('event_time', 'desc')
            ->get()
            ->groupBy('geofence_id');

        foreach ($activeGeofences as $geofence) {
            if (!$geofence->isActiveNow()) {
                continue;
            }

            $recentGeofenceEvents = $recentEvents->get($geofence->id, collect());
            $lastEvent = $recentGeofenceEvents->first();
            
            // Check if user just entered this geofence
            if ($this->shouldTriggerEnterEvent($geofence, $lastEvent)) {
                $event = $this->createGeofenceEvent(
                    $geofence,
                    $user,
                    'enter',
                    $latitude,
                    $longitude,
                    $locationData
                );
                
                $triggeredEvents[] = $event;
                $this->processGeofenceEvent($event);
            }
        }

        // Check for exit events (geofences user was in but no longer is)
        $this->checkForExitEvents($user, $activeGeofences, $latitude, $longitude, $locationData);

        // Check for dwell events
        $this->checkForDwellEvents($user, $activeGeofences, $latitude, $longitude, $locationData);

        return $triggeredEvents;
    }

    /**
     * Check if an enter event should be triggered.
     */
    private function shouldTriggerEnterEvent(Geofence $geofence, ?GeofenceEvent $lastEvent): bool
    {
        if (!$geofence->trigger_on_enter) {
            return false;
        }

        // If no recent events, this is an enter event
        if (!$lastEvent) {
            return true;
        }

        // If last event was exit and it was more than 5 minutes ago, this is a new enter
        if ($lastEvent->event_type === 'exit' && $lastEvent->event_time->diffInMinutes(now()) >= 5) {
            return true;
        }

        return false;
    }

    /**
     * Check for exit events.
     */
    private function checkForExitEvents(
        User $user,
        $currentGeofences,
        float $latitude,
        float $longitude,
        array $locationData
    ): void {
        $currentGeofenceIds = $currentGeofences->pluck('id')->toArray();
        
        // Get geofences user was recently in
        $recentEnterEvents = GeofenceEvent::where('user_id', $user->id)
            ->where('event_type', 'enter')
            ->where('event_time', '>=', now()->subHours(2))
            ->whereNotIn('geofence_id', $currentGeofenceIds)
            ->with('geofence')
            ->get();

        foreach ($recentEnterEvents as $enterEvent) {
            $geofence = $enterEvent->geofence;
            
            if (!$geofence || !$geofence->trigger_on_exit || !$geofence->isActiveNow()) {
                continue;
            }

            // Check if there's already a recent exit event
            $recentExitEvent = GeofenceEvent::where('user_id', $user->id)
                ->where('geofence_id', $geofence->id)
                ->where('event_type', 'exit')
                ->where('event_time', '>', $enterEvent->event_time)
                ->first();

            if (!$recentExitEvent) {
                $exitEvent = $this->createGeofenceEvent(
                    $geofence,
                    $user,
                    'exit',
                    $latitude,
                    $longitude,
                    $locationData
                );
                
                $this->processGeofenceEvent($exitEvent);
            }
        }
    }

    /**
     * Check for dwell events.
     */
    private function checkForDwellEvents(
        User $user,
        $currentGeofences,
        float $latitude,
        float $longitude,
        array $locationData
    ): void {
        foreach ($currentGeofences as $geofence) {
            if (!$geofence->trigger_on_dwell || !$geofence->dwell_time_seconds) {
                continue;
            }

            // Find the most recent enter event for this geofence
            $enterEvent = GeofenceEvent::where('user_id', $user->id)
                ->where('geofence_id', $geofence->id)
                ->where('event_type', 'enter')
                ->where('event_time', '>=', now()->subHours(2))
                ->orderBy('event_time', 'desc')
                ->first();

            if (!$enterEvent) {
                continue;
            }

            // Check if user has been in the geofence long enough
            $dwellTime = $enterEvent->event_time->diffInSeconds(now());
            
            if ($dwellTime >= $geofence->dwell_time_seconds) {
                // Check if dwell event already exists
                $existingDwellEvent = GeofenceEvent::where('user_id', $user->id)
                    ->where('geofence_id', $geofence->id)
                    ->where('event_type', 'dwell')
                    ->where('event_time', '>', $enterEvent->event_time)
                    ->first();

                if (!$existingDwellEvent) {
                    $dwellEvent = $this->createGeofenceEvent(
                        $geofence,
                        $user,
                        'dwell',
                        $latitude,
                        $longitude,
                        array_merge($locationData, [
                            'dwell_start_time' => $enterEvent->event_time,
                            'dwell_duration_seconds' => $dwellTime,
                        ])
                    );
                    
                    $this->processGeofenceEvent($dwellEvent);
                }
            }
        }
    }

    /**
     * Create a geofence event.
     */
    private function createGeofenceEvent(
        Geofence $geofence,
        User $user,
        string $eventType,
        float $latitude,
        float $longitude,
        array $locationData = []
    ): GeofenceEvent {
        $eventData = array_merge([
            'geofence_id' => $geofence->id,
            'user_id' => $user->id,
            'event_type' => $eventType,
            'latitude' => $latitude,
            'longitude' => $longitude,
            'event_time' => now(),
            'session_id' => $locationData['session_id'] ?? Str::uuid(),
        ], $locationData);

        // Add order context if available
        if (isset($locationData['order_id'])) {
            $eventData['order_id'] = $locationData['order_id'];
        }

        $event = GeofenceEvent::create($eventData);
        
        // Update geofence trigger count
        $geofence->incrementTriggerCount();

        return $event;
    }

    /**
     * Process a geofence event (send notifications, webhooks, etc.).
     */
    public function processGeofenceEvent(GeofenceEvent $event): void
    {
        $geofence = $event->geofence;
        $user = $event->user;
        
        // Send notifications
        $this->sendNotifications($event);
        
        // Send webhooks
        $this->sendWebhooks($event);
        
        // Broadcast real-time geofence event
        $this->realtimeService->broadcastGeofenceEvent($event, $geofence, $user, $event->order_id);
        
        // Log the event
        Log::info('Geofence event processed', [
            'event_id' => $event->id,
            'geofence_id' => $geofence->id,
            'geofence_name' => $geofence->name,
            'user_id' => $event->user_id,
            'event_type' => $event->event_type,
            'location' => [$event->latitude, $event->longitude],
        ]);
    }

    /**
     * Send notifications for geofence event.
     */
    private function sendNotifications(GeofenceEvent $event): void
    {
        $geofence = $event->geofence;
        $notificationTypes = $geofence->notification_types ?? [];
        
        if (empty($notificationTypes)) {
            return;
        }

        $message = $this->getNotificationMessage($event);
        $recipients = $this->getNotificationRecipients($geofence);
        $results = [];

        foreach ($notificationTypes as $type) {
            try {
                switch ($type) {
                    case 'push':
                        $result = $this->sendPushNotifications($recipients, $message, $event);
                        break;
                    case 'sms':
                        $result = $this->sendSmsNotifications($recipients, $message, $event);
                        break;
                    case 'email':
                        $result = $this->sendEmailNotifications($recipients, $message, $event);
                        break;
                    case 'in_app':
                        $result = $this->sendInAppNotifications($recipients, $message, $event);
                        break;
                    default:
                        $result = ['status' => 'unsupported', 'type' => $type];
                }
                
                $results[$type] = $result;
            } catch (\Exception $e) {
                $results[$type] = [
                    'status' => 'error',
                    'error' => $e->getMessage(),
                ];
                
                Log::error('Geofence notification failed', [
                    'event_id' => $event->id,
                    'type' => $type,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $event->markNotificationSent($results);
    }

    /**
     * Get notification message for event.
     */
    private function getNotificationMessage(GeofenceEvent $event): string
    {
        $geofence = $event->geofence;
        $user = $event->user;
        
        $message = match ($event->event_type) {
            'enter' => $geofence->enter_message,
            'exit' => $geofence->exit_message,
            'dwell' => $geofence->dwell_message,
            default => null,
        };

        if (!$message) {
            $message = match ($event->event_type) {
                'enter' => "{$user->name} entered {$geofence->name}",
                'exit' => "{$user->name} exited {$geofence->name}",
                'dwell' => "{$user->name} has been in {$geofence->name} for {$event->getDwellDurationFormatted()}",
                default => "{$user->name} triggered {$geofence->name}",
            };
        }

        // Replace placeholders
        $replacements = [
            '{user_name}' => $user->name,
            '{geofence_name}' => $geofence->name,
            '{event_type}' => $event->getEventTypeLabel(),
            '{event_time}' => $event->event_time->format('Y-m-d H:i:s'),
            '{location}' => $event->getFormattedAddress(),
            '{dwell_duration}' => $event->getDwellDurationFormatted() ?? '',
        ];

        return str_replace(array_keys($replacements), array_values($replacements), $message);
    }

    /**
     * Get notification recipients.
     */
    private function getNotificationRecipients(Geofence $geofence): array
    {
        $recipients = $geofence->notification_recipients ?? [];
        
        if (empty($recipients)) {
            // Default to geofence creator
            $recipients = [$geofence->created_by];
        }

        return User::whereIn('id', $recipients)->get()->toArray();
    }

    /**
     * Send push notifications.
     */
    private function sendPushNotifications(array $recipients, string $message, GeofenceEvent $event): array
    {
        $results = [];
        
        foreach ($recipients as $recipient) {
            if ($recipient['fcm_token']) {
                $result = $this->notificationService->sendPushNotification(
                    $recipient['fcm_token'],
                    'Geofence Alert',
                    $message,
                    [
                        'type' => 'geofence_event',
                        'event_id' => $event->id,
                        'geofence_id' => $event->geofence_id,
                        'event_type' => $event->event_type,
                    ]
                );
                
                $results[] = $result;
            }
        }

        return ['status' => 'sent', 'count' => count($results), 'results' => $results];
    }

    /**
     * Send SMS notifications.
     */
    private function sendSmsNotifications(array $recipients, string $message, GeofenceEvent $event): array
    {
        $results = [];
        
        foreach ($recipients as $recipient) {
            if ($recipient['phone_number']) {
                // Implement SMS sending logic here
                $results[] = [
                    'recipient' => $recipient['phone_number'],
                    'status' => 'sent', // This would be the actual result
                ];
            }
        }

        return ['status' => 'sent', 'count' => count($results), 'results' => $results];
    }

    /**
     * Send email notifications.
     */
    private function sendEmailNotifications(array $recipients, string $message, GeofenceEvent $event): array
    {
        $results = [];
        
        foreach ($recipients as $recipient) {
            // Implement email sending logic here
            $results[] = [
                'recipient' => $recipient['email'],
                'status' => 'sent', // This would be the actual result
            ];
        }

        return ['status' => 'sent', 'count' => count($results), 'results' => $results];
    }

    /**
     * Send in-app notifications.
     */
    private function sendInAppNotifications(array $recipients, string $message, GeofenceEvent $event): array
    {
        $results = [];
        
        foreach ($recipients as $recipient) {
            // Create in-app notification record
            \App\Models\Notification::create([
                'user_id' => $recipient['id'],
                'title' => 'Geofence Alert',
                'message' => $message,
                'type' => 'geofence_event',
                'data' => [
                    'event_id' => $event->id,
                    'geofence_id' => $event->geofence_id,
                    'event_type' => $event->event_type,
                ],
            ]);
            
            $results[] = [
                'recipient' => $recipient['id'],
                'status' => 'created',
            ];
        }

        return ['status' => 'sent', 'count' => count($results), 'results' => $results];
    }

    /**
     * Send webhooks for geofence event.
     */
    private function sendWebhooks(GeofenceEvent $event): void
    {
        $geofence = $event->geofence;
        $webhookUrls = $geofence->metadata['webhook_urls'] ?? [];
        
        if (empty($webhookUrls)) {
            return;
        }

        $payload = [
            'event_id' => $event->id,
            'geofence_id' => $geofence->id,
            'geofence_name' => $geofence->name,
            'geofence_type' => $geofence->type,
            'user_id' => $event->user_id,
            'user_name' => $event->user->name,
            'event_type' => $event->event_type,
            'latitude' => $event->latitude,
            'longitude' => $event->longitude,
            'address' => $event->address,
            'event_time' => $event->event_time->toISOString(),
            'accuracy_meters' => $event->accuracy_meters,
            'speed_kmh' => $event->speed_kmh,
            'bearing_degrees' => $event->bearing_degrees,
            'dwell_duration_seconds' => $event->dwell_duration_seconds,
            'order_id' => $event->order_id,
            'metadata' => $event->metadata,
        ];

        foreach ($webhookUrls as $url) {
            try {
                $response = Http::timeout(10)->post($url, $payload);
                
                Log::info('Geofence webhook sent', [
                    'event_id' => $event->id,
                    'url' => $url,
                    'status' => $response->status(),
                ]);
            } catch (\Exception $e) {
                Log::error('Geofence webhook failed', [
                    'event_id' => $event->id,
                    'url' => $url,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $event->markWebhookSent();
    }

    /**
     * Create a delivery zone geofence.
     */
    public function createDeliveryZone(
        User $creator,
        string $name,
        float $latitude,
        float $longitude,
        int $radiusMeters,
        array $options = []
    ): Geofence {
        return Geofence::create(array_merge([
            'created_by' => $creator->id,
            'name' => $name,
            'type' => 'delivery_zone',
            'shape' => 'circle',
            'center_latitude' => $latitude,
            'center_longitude' => $longitude,
            'radius_meters' => $radiusMeters,
            'trigger_on_enter' => true,
            'trigger_on_exit' => true,
            'notification_types' => ['push', 'in_app'],
            'notification_recipients' => [$creator->id],
        ], $options));
    }

    /**
     * Get geofence analytics.
     */
    public function getGeofenceAnalytics(Geofence $geofence, int $days = 30): array
    {
        $events = $geofence->events()
            ->where('event_time', '>=', now()->subDays($days))
            ->with('user')
            ->get();

        return [
            'geofence' => [
                'id' => $geofence->id,
                'name' => $geofence->name,
                'type' => $geofence->type,
                'area_sqm' => $geofence->getArea(),
                'trigger_count' => $geofence->trigger_count,
                'last_triggered' => $geofence->last_triggered_at,
            ],
            'events' => [
                'total' => $events->count(),
                'enter' => $events->where('event_type', 'enter')->count(),
                'exit' => $events->where('event_type', 'exit')->count(),
                'dwell' => $events->where('event_type', 'dwell')->count(),
            ],
            'users' => [
                'unique_visitors' => $events->pluck('user_id')->unique()->count(),
                'most_active_user' => $events->groupBy('user_id')->map->count()->sortDesc()->keys()->first(),
                'avg_visits_per_user' => $events->count() / max($events->pluck('user_id')->unique()->count(), 1),
            ],
            'timing' => [
                'avg_dwell_time' => $events->where('event_type', 'dwell')->avg('dwell_duration_seconds'),
                'peak_hour' => $events->groupBy(function ($event) {
                    return $event->event_time->format('H');
                })->map->count()->sortDesc()->keys()->first(),
                'busiest_day' => $events->groupBy(function ($event) {
                    return $event->event_time->format('Y-m-d');
                })->map->count()->sortDesc()->keys()->first(),
            ],
            'accuracy' => [
                'avg_accuracy' => $events->whereNotNull('accuracy_meters')->avg('accuracy_meters'),
                'high_accuracy_events' => $events->where('accuracy_meters', '<=', 50)->count(),
            ],
        ];
    }
}