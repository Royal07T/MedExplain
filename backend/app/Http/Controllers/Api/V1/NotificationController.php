<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\NotificationResource;
use App\Models\Notification;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * User-facing in-app notifications. All access is user-scoped; a user can
 * only ever see or modify their own notifications.
 */
final class NotificationController extends Controller
{
    public function __construct(private readonly NotificationService $notifications) {}

    /**
     * List the authenticated user's notifications with the unread count.
     */
    public function index(Request $request): JsonResponse
    {
        $items = Notification::query()
            ->where('user_id', $request->user()->id)
            ->latest('created_at')
            ->limit(50)
            ->get();

        return response()->json([
            'data' => NotificationResource::collection($items),
            'unread_count' => $this->notifications->unreadCount($request->user()),
        ]);
    }

    /**
     * A lightweight unread count for the notification badge.
     */
    public function unreadCount(Request $request): JsonResponse
    {
        return response()->json([
            'unread_count' => $this->notifications->unreadCount($request->user()),
        ]);
    }

    /**
     * Mark a single notification as read. User-scoped.
     */
    public function markRead(Request $request, Notification $notification): JsonResponse
    {
        abort_if($notification->user_id !== $request->user()->id, 403, 'You do not own this notification.');

        if ($notification->read_at === null) {
            $notification->update(['read_at' => now()]);
        }

        return response()->json(new NotificationResource($notification));
    }

    /**
     * Mark every unread notification as read.
     */
    public function markAllRead(Request $request): JsonResponse
    {
        Notification::query()
            ->where('user_id', $request->user()->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return response()->json(['unread_count' => 0]);
    }
}