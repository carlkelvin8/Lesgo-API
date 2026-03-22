<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/v1/notifications",
     *     summary="List notifications for the authenticated user",
     *     tags={"Notifications"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="unread_only", in="query", required=false, @OA\Schema(type="boolean")),
     *     @OA\Response(response=200, description="Paginated notifications")
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $query = Notification::where('user_id', $request->user()->id)
            ->orderByDesc('id');

        if ($request->boolean('unread_only')) {
            $query->unread();
        }

        return $this->success($query->paginate(20));
    }

    /**
     * @OA\Get(
     *     path="/api/v1/notifications/unread-count",
     *     summary="Get unread notification count",
     *     tags={"Notifications"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(response=200, description="Unread count")
     * )
     */
    public function unreadCount(Request $request): JsonResponse
    {
        $count = Notification::where('user_id', $request->user()->id)
            ->unread()
            ->count();

        return $this->success(['unread_count' => $count]);
    }

    /**
     * @OA\Patch(
     *     path="/api/v1/notifications/{id}/read",
     *     summary="Mark a notification as read",
     *     tags={"Notifications"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Marked as read"),
     *     @OA\Response(response=404, description="Not found")
     * )
     */
    public function markRead(Request $request, int $id): JsonResponse
    {
        $updated = NotificationService::markRead($id, $request->user()->id);

        if (!$updated) {
            return $this->error('Notification not found or already read', 404);
        }

        return $this->message('Notification marked as read');
    }

    /**
     * @OA\Post(
     *     path="/api/v1/notifications/read-all",
     *     summary="Mark all notifications as read",
     *     tags={"Notifications"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(response=200, description="All marked as read")
     * )
     */
    public function markAllRead(Request $request): JsonResponse
    {
        $count = NotificationService::markAllRead($request->user()->id);

        return $this->message("{$count} notifications marked as read");
    }
}
