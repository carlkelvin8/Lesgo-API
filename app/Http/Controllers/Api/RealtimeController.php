<?php

namespace App\Http\Controllers\Api;

use App\Models\WebSocketConnection;
use App\Models\RealtimeNotification;
use App\Models\User;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class RealtimeController extends Controller
{
    /**
     * Register a realtime connection.
     */
    public function connect(Request $request): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validate([
            'device_name' => 'nullable|string|max:255',
            'platform' => 'nullable|in:web,mobile,desktop',
        ]);

        // Create or update connection record
        $connection = WebSocketConnection::updateOrCreate(
            [
                'user_id' => $user->id,
                'socket_id' => $request->input('socket_id', Str::uuid()->toString()),
            ],
            [
                'device_name' => $validated['device_name'] ?? $request->userAgent(),
                'platform' => $validated['platform'] ?? 'web',
                'ip_address' => $request->ip(),
                'connected_at' => now(),
                'is_active' => true,
            ]
        );

        return $this->success([
            'connection_id' => $connection->socket_id,
            'user_id' => $user->id,
            'connected_at' => $connection->connected_at->toISOString(),
            'ping_interval_seconds' => 30,
        ], 'Connected to realtime service');
    }

    /**
     * Disconnect from realtime service.
     */
    public function disconnect(Request $request): JsonResponse
    {
        $user = $request->user();
        $socketId = $request->input('socket_id');

        if ($socketId) {
            WebSocketConnection::where('user_id', $user->id)
                ->where('socket_id', $socketId)
                ->update([
                    'is_active' => false,
                    'disconnected_at' => now(),
                ]);
        } else {
            WebSocketConnection::where('user_id', $user->id)
                ->where('is_active', true)
                ->update([
                    'is_active' => false,
                    'disconnected_at' => now(),
                ]);
        }

        return $this->success(['disconnected_at' => now()->toISOString()], 'Disconnected from realtime service');
    }

    /**
     * Ping/pong for keep-alive.
     */
    public function ping(Request $request): JsonResponse
    {
        $user = $request->user();
        $socketId = $request->input('socket_id');

        if ($socketId) {
            WebSocketConnection::where('user_id', $user->id)
                ->where('socket_id', $socketId)
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

        $validated = $request->validate([
            'user_id' => 'nullable|integer',
            'platform' => 'nullable|in:web,mobile,desktop',
        ]);

        $query = WebSocketConnection::where('is_active', true)
            ->with('user:id,name,email');

        if (!empty($validated['user_id'])) {
            $query->where('user_id', $validated['user_id']);
        }

        if (!empty($validated['platform'])) {
            $query->where('platform', $validated['platform']);
        }

        $connections = $query->orderByDesc('connected_at')->get()->map(function ($conn) {
            return [
                'connection_id' => $conn->socket_id,
                'user_id' => $conn->user_id,
                'user_name' => $conn->user->name,
                'user_email' => $conn->user->email,
                'device_name' => $conn->device_name,
                'platform' => $conn->platform,
                'ip_address' => $conn->ip_address,
                'connected_at' => $conn->connected_at->toISOString(),
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

        $notification->update(['read_at' => now()]);

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

        $activeConnections = WebSocketConnection::where('is_active', true)->count();
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
     * Send a test notification.
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
        ]);

        return $this->success([
            'notification_id' => $notification->id,
            'sent_at' => $notification->created_at->toISOString(),
        ], 'Test notification sent');
    }

    /**
     * Helper: Get peak connection hour.
     */
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
