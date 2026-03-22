<?php

namespace App\Services;

use App\Jobs\SendPushNotificationJob;
use App\Jobs\SendSmsJob;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class NotificationService
{
    /**
     * Persist an in-app notification and optionally dispatch push/SMS/email.
     */
    public static function send(
        User|int $user,
        string $type,
        string $title,
        string $body,
        array $data = [],
        string $channel = 'in_app'
    ): Notification {
        $userId = $user instanceof User ? $user->id : $user;

        $notification = Notification::create([
            'user_id' => $userId,
            'type'    => $type,
            'title'   => $title,
            'body'    => $body,
            'data'    => $data,
            'channel' => $channel,
        ]);

        // Push notification hook — swap in FCM/APNs when ready
        if (in_array($channel, ['push', 'all'])) {
            static::dispatchPush($userId, $title, $body, $data);
        }

        return $notification;
    }

    /**
     * Mark a single notification as read.
     */
    public static function markRead(int $notificationId, int $userId): bool
    {
        return (bool) Notification::where('id', $notificationId)
            ->where('user_id', $userId)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
    }

    /**
     * Mark all notifications as read for a user.
     */
    public static function markAllRead(int $userId): int
    {
        return Notification::where('user_id', $userId)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
    }

    /**
     * Dispatch an SMS to a phone number (queued).
     */
    public static function sendSms(string $phone, string $message): void
    {
        SendSmsJob::dispatch($phone, $message);
    }

    /**
     * Dispatch FCM push notification with optional SMS fallback.
     */
    private static function dispatchPush(int $userId, string $title, string $body, array $data): void
    {
        SendPushNotificationJob::dispatch($userId, $title, $body, $data, smsFallback: true);
    }
}
