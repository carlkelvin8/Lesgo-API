<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\RealtimeService;
use App\Models\WebSocketConnection;
use App\Models\RealtimeNotification;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class RealtimeController extends Controller
{
    public function __construct(
        private RealtimeService $realtimeService
    ) {}

    /**
     * Register WebSocket connection
     */
    public function connect(Request $request): JsonResponse
    {
        $request->validate([
            'channel' => 'required|string|in:orders,chat,notifications,location,general',
            'metadata' => 'nullable|array',
        ]);

        $user = Auth::user();
        $connection = $this->realtimeService->registerConnection(
            $user,
            $request->channel,
            $request->metadata ?? []
        );

        return response()->json([
            'success' => true,
            'message' => 'WebSocket connection registered successfully',
            'request_id' => $request->header('X-Request-ID', uniqid()),
            'data' => [
                'connection_id' => $connection->connection_id,
                'channel' => $connection->channel,
                'status' => $connection->status,
                'connected_at' => $connection->connected_at->toISOString(),
            ],
        ]);
    }

    /**
     * Disconnect WebSocket connection
     */
    public function disconnect(Request $request): JsonResponse
    {
        $request->validate([
            'connection_id' => 'required|string|exists:websocket_connections,connection_id',
        ]);

        $this->realtimeService->disconnectConnection($request->connection_id);

        return response()->json([
            'success' => true,
            'message' => 'WebSocket connection disconnected successfully',
            'request_id' => $request->header('X-Request-ID', uniqid()),
            'data' => null,
        ]);
    }

    /**
     * Ping WebSocket connection
     */
    public function ping(Request $request): JsonResponse
    {
        $request->validate([
            'connection_id' => 'required|string|exists:websocket_connections,connection_id',
        ]);

        $this->realtimeService->pingConnection($request->connection_id);

        return response()->json([
            'success' => true,
            'message' => 'Connection ping updated',
            'request_id' => $request->header('X-Request-ID', uniqid()),
            'data' => [
                'timestamp' => now()->toISOString(),
            ],
        ]);
    }

    /**
     * Get user's active connections
     */
    public function connections(Request $request): JsonResponse
    {
        $user = Auth::user();
        $connections = $this->realtimeService->getUserConnections($user->id);

        return response()->json([
            'success' => true,
            'message' => 'Active connections retrieved successfully',
            'request_id' => $request->header('X-Request-ID', uniqid()),
            'data' => [
                'connections' => $connections,
                'total' => count($connections),
            ],
        ]);
    }

    /**
     * Get real-time notifications
     */
    public function notifications(Request $request): JsonResponse
    {
        $user = Auth::user();
        
        $query = RealtimeNotification::where('user_id', $user->id)
            ->orderBy('created_at', 'desc');

        // Filter by type
        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        // Filter by read status
        if ($request->has('unread_only') && $request->boolean('unread_only')) {
            $query->unread();
        }

        // Filter by priority
        if ($request->has('priority')) {
            $query->where('priority', $request->priority);
        }

        // Filter by time range
        if ($request->has('hours')) {
            $query->where('created_at', '>=', now()->subHours($request->hours));
        }

        $notifications = $query->paginate($request->get('per_page', 20));

        return response()->json([
            'success' => true,
            'message' => 'Real-time notifications retrieved successfully',
            'request_id' => $request->header('X-Request-ID', uniqid()),
            'data' => [
                'notifications' => $notifications->items(),
                'pagination' => [
                    'current_page' => $notifications->currentPage(),
                    'last_page' => $notifications->lastPage(),
                    'per_page' => $notifications->perPage(),
                    'total' => $notifications->total(),
                ],
                'unread_count' => RealtimeNotification::where('user_id', $user->id)
                    ->unread()
                    ->count(),
            ],
        ]);
    }

    /**
     * Mark notification as read
     */
    public function markNotificationRead(Request $request, int $notificationId): JsonResponse
    {
        $user = Auth::user();
        
        $notification = RealtimeNotification::where('id', $notificationId)
            ->where('user_id', $user->id)
            ->firstOrFail();

        $notification->markAsRead();

        return response()->json([
            'success' => true,
            'message' => 'Notification marked as read',
            'request_id' => $request->header('X-Request-ID', uniqid()),
            'data' => [
                'notification_id' => $notification->id,
                'read_at' => $notification->read_at->toISOString(),
            ],
        ]);
    }

    /**
     * Mark all notifications as read
     */
    public function markAllNotificationsRead(Request $request): JsonResponse
    {
        $user = Auth::user();
        
        $count = RealtimeNotification::where('user_id', $user->id)
            ->unread()
            ->update(['read_at' => now()]);

        return response()->json([
            'success' => true,
            'message' => 'All notifications marked as read',
            'request_id' => $request->header('X-Request-ID', uniqid()),
            'data' => [
                'marked_count' => $count,
                'timestamp' => now()->toISOString(),
            ],
        ]);
    }

    /**
     * Get connection statistics (admin only)
     */
    public function stats(Request $request): JsonResponse
    {
        $user = Auth::user();
        
        if (!$user->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access',
                'request_id' => $request->header('X-Request-ID', uniqid()),
                'data' => null,
            ], 403);
        }

        $stats = $this->realtimeService->getConnectionStats();

        return response()->json([
            'success' => true,
            'message' => 'Connection statistics retrieved successfully',
            'request_id' => $request->header('X-Request-ID', uniqid()),
            'data' => $stats,
        ]);
    }

    /**
     * Test real-time notification (development only)
     */
    public function testNotification(Request $request): JsonResponse
    {
        if (app()->environment('production')) {
            return response()->json([
                'success' => false,
                'message' => 'Test endpoint not available in production',
                'request_id' => $request->header('X-Request-ID', uniqid()),
                'data' => null,
            ], 403);
        }

        $request->validate([
            'title' => 'required|string|max:255',
            'message' => 'required|string|max:1000',
            'type' => 'nullable|string|max:50',
            'priority' => 'nullable|in:low,normal,high,urgent',
            'data' => 'nullable|array',
        ]);

        $user = Auth::user();
        
        $notification = $this->realtimeService->createRealtimeNotification(
            $user->id,
            $request->get('type', 'test'),
            $request->title,
            $request->message,
            $request->get('data', []),
            $request->get('priority', 'normal')
        );

        return response()->json([
            'success' => true,
            'message' => 'Test notification sent successfully',
            'request_id' => $request->header('X-Request-ID', uniqid()),
            'data' => [
                'notification_id' => $notification->id,
                'sent_at' => $notification->sent_at->toISOString(),
            ],
        ]);
    }
}