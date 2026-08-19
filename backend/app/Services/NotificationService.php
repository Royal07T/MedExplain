<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\User;

/**
 * Creates and queries in-app notifications for a user.
 */
final class NotificationService
{
    /**
     * Create a notification for the given user.
     *
     * @param  array<string, mixed>|null  $data  structured context (ids, names)
     */
    public function notify(
        User $user,
        string $title,
        ?string $body = null,
        string $type = 'system',
        ?array $data = null,
    ): Notification {
        return Notification::create([
            'user_id' => $user->id,
            'title' => $title,
            'body' => $body,
            'type' => $type,
            'data' => $data,
        ]);
    }

    /**
     * Count the user's unread notifications.
     */
    public function unreadCount(User $user): int
    {
        return Notification::query()
            ->where('user_id', $user->id)
            ->whereNull('read_at')
            ->count();
    }
}