<?php

namespace App\Http\Controllers\Api;

use App\Models\WebSocketConnection;
use App\Models\RealtimeNotification;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Str;

class RealtimeController extends Controller
{
    /**
     * WebSocket / Reverb client configuration for mobile apps.
     */
    public function config(Request $request): JsonResponse
    {
        return $this->success([
            'websocket' => $this->websocketConfig(),
            'ping_interval_seconds' => 30,
        ], 'Realtime config retrieved');
    }

    /**
     * Register a realtime session (HTTP keep-alive + optional Reverb).
     */
    public function connect(Request $request): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validate([
            'device_name' => 'nullable|string|max:255',
            'platform' => 'nullable|in:web,mobile,desktop',
            'channel' => 'nullable|string|max:100',
        ]);

        $connectionId = $request->input('connection_id', Str::uuid()->toString());
        $channel = $validated['channel'] ?? "user.{$user->id}";

        WebSocketConnection::where('user_id', $user->id)
            ->where('status', 'connected')
            ->update([
                'status' => 'disconnected',
                'disconnected_at' => now(),
            ]);

        $connection = WebSocketConnection::create([
            'user_id' => $user->id,
            'connection_id' => $connectionId,
            'channel' => $channel,
            'status' => 'connected',
            'connected_at' => now(),
            'last_ping_at' => now(),
            'metadata' => [
                'device_name' => $validated['device_name'] ?? $request->userAgent(),
                'platform' => $validated['platform'] ?? 'mobile',
                'ip_address' => $request->ip(),
            ],
        ]);

        return $this->success([
            'connection_id' => $connection->connection_id,
            'user_id' => $user->id,
            'channel' => $connection->channel,
            'connected_at' => $connection->connected_at->toISOString(),
            'ping_interval_seconds' => 30,
            'websocket' => $this->websocketConfig(),
        ], 'Connected to realtime service');
    }

    /**
     * Authorize private WebSocket channels (Laravel Reverb / Pusher protocol).
     */
    public function broadcastAuth(Request $request): JsonResponse|\Illuminate\Http\Response
    {
        return Broadcast::auth($request);
    }

    /**
     * Disconnect from realtime service.
     */
    public function disconnect(Request $request): JsonResponse
    {
        $user = $request->user();
        $connectionId = $request->input('connection_id') ?? $request->input('socket_id');

        $query = WebSocketConnection::where('user_id', $user->id)
            ->where('status', 'connected');

        if ($connectionId) {
            $query->where('connection_id', $connectionId);
        }

        $query->update([
            'status' => 'disconnected',
            'disconnected_at' => now(),
        ]);

        return $this->success(['disconnected_at' => now()->toISOString()], 'Disconnected from realtime service');
    }

    /**
     * Ping/pong for keep-alive.
     */
    public function ping(Request $request): JsonResponse
    {
        $user = $request->user();
        $connectionId = $request->input('connection_id') ?? $request->input('socket_id');

        if ($connectionId) {
            WebSocketConnection::where('user_id', $user->id)
                ->where('connection_id', $connectionId)
                ->where('status', 'connected')
                ->update(['last_ping_at' => now()]);
        }

        return $this->success([
            'timestamp' => now()->toISOString(),
            'latency_ms' => 0,
        ], 'Pong');
    }

    /**
     * Get active connections (admin only).
     */
    public function connections(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user->isAdmin()) {
            return $this->error('Forbidden', 403);
        }

        $connections = WebSocketConnection::connected()
            ->with('user:id,name,email')
            ->orderByDesc('connected_at')
            ->get()
            ->map(function ($conn) {
                $metadata = $conn->metadata ?? [];

                return [
                    'connection_id' => $conn->connection_id,
                    'channel' => $conn->channel,
                    'user_id' => $conn->user_id,
                    'user_name' => $conn->user?->name,
                    'user_email' => $conn->user?->email,
                    'device_name' => $metadata['device_name'] ?? null,
                    'platform' => $metadata['platform'] ?? null,
                    'ip_address' => $metadata['ip_address'] ?? null,
                    'connected_at' => $conn->connected_at?->toISOString(),
                    'last_ping_at' => $conn->last_ping_at?->toISOString(),
                ];
            });

        return $this->success([
            'total_connections' => $connections->count(),
            'connections' => $connections,
        ], 'Active connections retrieved');
    }

    /**
     * Get realtime notifications for current user.
     */
    public function notifications(Request $request): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validate([
            'unread_only' => 'nullable|boolean',
            'type' => 'nullable|string',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $query = RealtimeNotification::where('user_id', $user->id)
            ->orderByDesc('created_at');

        if (!empty($validated['unread_only']) && $validated['unread_only']) {
            $query->whereNull('read_at');
        }

        if (!empty($validated['type'])) {
            $query->where('type', $validated['type']);
        }

        $perPage = (int) ($validated['per_page'] ?? 20);
        $notifications = $query->paginate($perPage);

        return $this->success($notifications, 'Realtime notifications retrieved');
    }

    /**
     * Mark a notification as read.
     */
    public function markNotificationRead(Request $request, $notificationId): JsonResponse
    {
        $user = $request->user();

        $notification = RealtimeNotification::where('user_id', $user->id)
            ->find($notificationId);

        if (!$notification) {
            return $this->error('Notification not found.', 404);
        }

        $notification->markAsRead();

        return $this->success([
            'notification_id' => $notificationId,
            'read_at' => $notification->read_at->toISOString(),
        ], 'Notification marked as read');
    }

    /**
     * Mark all notifications as read.
     */
    public function markAllNotificationsRead(Request $request): JsonResponse
    {
        $user = $request->user();

        $updated = RealtimeNotification::where('user_id', $user->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return $this->success([
            'marked_count' => $updated,
        ], 'All notifications marked as read');
    }

    /**
     * Get realtime connection statistics.
     */
    public function stats(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user->isAdmin()) {
            return $this->error('Forbidden', 403);
        }

        $activeConnections = WebSocketConnection::connected()->active()->count();
        $totalConnectionsToday = WebSocketConnection::whereDate('connected_at', today())->count();
        $totalNotificationsSent = RealtimeNotification::whereDate('created_at', today())->count();
        $unreadNotifications = RealtimeNotification::whereNull('read_at')->count();

        $startTime = WebSocketConnection::min('connected_at');
        $uptime = $startTime ? $startTime->diffForHumans(now(), true) : '0 seconds';

        return $this->success([
            'active_connections' => $activeConnections,
            'total_connections_today' => $totalConnectionsToday,
            'total_notifications_sent' => $totalNotificationsSent,
            'unread_notifications' => $unreadNotifications,
            'uptime' => $uptime,
            'peak_hour' => $this->getPeakHour(),
        ], 'Realtime stats retrieved');
    }

    /**
     * Send a test notification (also broadcasts over WebSocket when configured).
     */
    public function testNotification(Request $request): JsonResponse
    {
        $user = $request->user();

        $notification = RealtimeNotification::create([
            'user_id' => $user->id,
            'type' => 'test',
            'title' => 'Test Notification',
            'message' => 'This is a test notification sent at ' . now()->format('Y-m-d H:i:s'),
            'data' => [
                'test' => true,
                'timestamp' => now()->toISOString(),
            ],
            'priority' => 'low',
            'channel' => 'websocket',
            'is_realtime' => true,
        ]);

        $notification->markAsSent();
        broadcast(new \App\Events\RealtimeNotificationSent($notification, $user));

        return $this->success([
            'notification_id' => $notification->id,
            'sent_at' => $notification->sent_at?->toISOString(),
        ], 'Test notification sent');
    }

    private function websocketConfig(): array
    {
        $key = config('broadcasting.connections.reverb.key');
        $host = config('broadcasting.connections.reverb.options.host');

        if (empty($key) || empty($host)) {
            return ['enabled' => false];
        }

        $scheme = config('broadcasting.connections.reverb.options.scheme', 'https');
        $port = (int) config('broadcasting.connections.reverb.options.port', 443);

        return [
            'enabled' => true,
            'driver' => 'reverb',
            'key' => $key,
            'host' => $host,
            'port' => $port,
            'scheme' => $scheme,
            'use_tls' => $scheme === 'https',
            'auth_endpoint' => url('/api/v1/broadcasting/auth'),
            'events' => [
                'chat_message' => 'chat.message.sent',
                'notification' => 'notification.sent',
            ],
        ];
    }

    private function getPeakHour(): ?string
    {
        $peakHour = WebSocketConnection::selectRaw('HOUR(connected_at) as hour, COUNT(*) as count')
            ->where('connected_at', '>=', now()->subDays(7))
            ->groupBy('hour')
            ->orderByDesc('count')
            ->first();

        return $peakHour ? $peakHour->hour . ':00' : null;
    }
}
