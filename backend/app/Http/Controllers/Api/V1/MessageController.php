<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Message;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

final class MessageController extends Controller
{
    /**
     * Get conversations for the authenticated user.
     */
    public function conversations(Request $request): JsonResponse
    {
        $user = $request->user();
        $organizationId = $user?->organization_id;

        if (!$organizationId) {
            return response()->json([
                'success' => false,
                'message' => 'No organization context',
            ], 403);
        }

        // Get all users the current user has conversations with
        $conversationUsers = User::where('organization_id', $organizationId)
            ->where('id', '!=', $user->id)
            ->whereHas('sentMessages', function ($query) use ($user, $organizationId) {
                $query->where('receiver_id', $user->id)
                    ->where('organization_id', $organizationId);
            })
            ->orWhereHas('receivedMessages', function ($query) use ($user, $organizationId) {
                $query->where('sender_id', $user->id)
                    ->where('organization_id', $organizationId);
            })
            ->get();

        $conversations = $conversationUsers->map(function ($otherUser) use ($user, $organizationId) {
            $lastMessage = Message::where('organization_id', $organizationId)
                ->where(function ($query) use ($user, $otherUser) {
                    $query->where(function ($q) use ($user, $otherUser) {
                        $q->where('sender_id', $user->id)
                            ->where('receiver_id', $otherUser->id);
                    })->orWhere(function ($q) use ($user, $otherUser) {
                        $q->where('sender_id', $otherUser->id)
                            ->where('receiver_id', $user->id);
                    });
                })
                ->latest()
                ->first();

            $unreadCount = Message::where('organization_id', $organizationId)
                ->where('sender_id', $otherUser->id)
                ->where('receiver_id', $user->id)
                ->where('is_read', false)
                ->count();

            return [
                'user_id' => $otherUser->id,
                'name' => $otherUser->name,
                'email' => $otherUser->email,
                'role' => $otherUser->role,
                'last_message' => $lastMessage?->content,
                'last_message_at' => $lastMessage?->created_at?->toISOString(),
                'unread_count' => $unreadCount,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $conversations,
        ]);
    }

    /**
     * Get messages between authenticated user and another user.
     */
    public function index(Request $request, $userId): JsonResponse
    {
        $user = $request->user();
        $organizationId = $user?->organization_id;

        if (!$organizationId) {
            return response()->json([
                'success' => false,
                'message' => 'No organization context',
            ], 403);
        }

        $otherUser = User::where('organization_id', $organizationId)
            ->where('id', $userId)
            ->firstOrFail();

        $messages = Message::where('organization_id', $organizationId)
            ->where(function ($query) use ($user, $otherUser) {
                $query->where(function ($q) use ($user, $otherUser) {
                    $q->where('sender_id', $user->id)
                        ->where('receiver_id', $otherUser->id);
                })->orWhere(function ($q) use ($user, $otherUser) {
                    $q->where('sender_id', $otherUser->id)
                        ->where('receiver_id', $user->id);
                });
            })
            ->latest()
            ->get();

        // Mark messages as read
        Message::where('organization_id', $organizationId)
            ->where('sender_id', $otherUser->id)
            ->where('receiver_id', $user->id)
            ->where('is_read', false)
            ->update(['is_read' => true, 'read_at' => now()]);

        return response()->json([
            'success' => true,
            'data' => $messages->map(function ($message) {
                return [
                    'id' => $message->id,
                    'sender_id' => $message->sender_id,
                    'sender_name' => $message->sender?->name,
                    'content' => $message->content,
                    'is_read' => $message->is_read,
                    'read_at' => $message->read_at?->toISOString(),
                    'created_at' => $message->created_at?->toISOString(),
                ];
            }),
        ]);
    }

    /**
     * Send a new message.
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'receiver_id' => ['required', 'exists:users,id'],
            'content' => ['required', 'string', 'max:2000'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $user = $request->user();
        $organizationId = $user?->organization_id;

        if (!$organizationId) {
            return response()->json([
                'success' => false,
                'message' => 'No organization context',
            ], 403);
        }

        $receiver = User::where('organization_id', $organizationId)
            ->where('id', $request->receiver_id)
            ->firstOrFail();

        if ($receiver->id === $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot send message to yourself',
            ], 400);
        }

        $message = Message::create([
            'sender_id' => $user->id,
            'receiver_id' => $receiver->id,
            'organization_id' => $organizationId,
            'content' => $request->content,
            'is_read' => false,
        ]);

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $message->id,
                'content' => $message->content,
                'created_at' => $message->created_at?->toISOString(),
                'message' => 'Message sent successfully',
            ],
        ], 201);
    }

    /**
     * Mark message as read.
     */
    public function markAsRead(Request $request, $id): JsonResponse
    {
        $user = $request->user();
        $organizationId = $user?->organization_id;

        if (!$organizationId) {
            return response()->json([
                'success' => false,
                'message' => 'No organization context',
            ], 403);
        }

        $message = Message::where('id', $id)
            ->where('organization_id', $organizationId)
            ->where('receiver_id', $user->id)
            ->firstOrFail();

        $message->is_read = true;
        $message->read_at = now();
        $message->save();

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $message->id,
                'is_read' => $message->is_read,
                'read_at' => $message->read_at?->toISOString(),
            ],
        ]);
    }
}
