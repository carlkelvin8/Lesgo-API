<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\Order;
use App\Services\RealtimeService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ChatController extends Controller
{
    public function __construct(
        private RealtimeService $realtimeService
    ) {}

    /**
     * Get or create conversation for an order
     */
    public function getOrCreateConversation(Request $request, int $orderId): JsonResponse
    {
        $user = Auth::user();
        
        $order = Order::findOrFail($orderId);
        
        // Check if user is part of this order
        if ($order->customer_id !== $user->id && $order->driver_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access to this conversation',
                'request_id' => $request->header('X-Request-ID', uniqid()),
                'data' => null,
            ], 403);
        }

        // Get or create conversation
        $conversation = ChatConversation::firstOrCreate(
            ['order_id' => $orderId],
            [
                'customer_id' => $order->customer_id,
                'driver_id' => $order->driver_id,
                'status' => 'active',
            ]
        );

        // Load relationships
        $conversation->load(['customer', 'driver', 'order']);

        return response()->json([
            'success' => true,
            'message' => 'Conversation retrieved successfully',
            'request_id' => $request->header('X-Request-ID', uniqid()),
            'data' => [
                'conversation' => [
                    'id' => $conversation->id,
                    'order_id' => $conversation->order_id,
                    'customer_id' => $conversation->customer_id,
                    'driver_id' => $conversation->driver_id,
                    'status' => $conversation->status,
                    'last_message_at' => $conversation->last_message_at?->toISOString(),
                    'created_at' => $conversation->created_at->toISOString(),
                    'customer' => [
                        'id' => $conversation->customer->id,
                        'name' => $conversation->customer->name,
                        'role' => $conversation->customer->role,
                    ],
                    'driver' => $conversation->driver ? [
                        'id' => $conversation->driver->id,
                        'name' => $conversation->driver->name,
                        'role' => $conversation->driver->role,
                    ] : null,
                    'order' => [
                        'id' => $conversation->order->id,
                        'status' => $conversation->order->status,
                        'service_id' => $conversation->order->service_id,
                    ],
                ],
            ],
        ]);
    }

    /**
     * Get user's conversations
     */
    public function conversations(Request $request): JsonResponse
    {
        $user = Auth::user();
        
        $query = ChatConversation::forUser($user->id)
            ->with(['customer', 'driver', 'order', 'latestMessage'])
            ->orderBy('last_message_at', 'desc');

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $conversations = $query->paginate($request->get('per_page', 20));

        return response()->json([
            'success' => true,
            'message' => 'Conversations retrieved successfully',
            'request_id' => $request->header('X-Request-ID', uniqid()),
            'data' => [
                'conversations' => $conversations->items(),
                'pagination' => [
                    'current_page' => $conversations->currentPage(),
                    'last_page' => $conversations->lastPage(),
                    'per_page' => $conversations->perPage(),
                    'total' => $conversations->total(),
                ],
            ],
        ]);
    }

    /**
     * Get messages for a conversation
     */
    public function messages(Request $request, int $conversationId): JsonResponse
    {
        $user = Auth::user();
        
        $conversation = ChatConversation::findOrFail($conversationId);
        
        // Check if user is part of this conversation
        if ($conversation->customer_id !== $user->id && $conversation->driver_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access to this conversation',
                'request_id' => $request->header('X-Request-ID', uniqid()),
                'data' => null,
            ], 403);
        }

        $query = ChatMessage::where('conversation_id', $conversationId)
            ->with('sender')
            ->orderBy('created_at', 'desc');

        // Filter by message type
        if ($request->has('type')) {
            $query->where('message_type', $request->type);
        }

        // Filter by sender
        if ($request->has('sender_id')) {
            $query->where('sender_id', $request->sender_id);
        }

        $messages = $query->paginate($request->get('per_page', 50));

        // Mark messages as read for the current user
        ChatMessage::where('conversation_id', $conversationId)
            ->where('sender_id', '!=', $user->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return response()->json([
            'success' => true,
            'message' => 'Messages retrieved successfully',
            'request_id' => $request->header('X-Request-ID', uniqid()),
            'data' => [
                'messages' => $messages->items(),
                'pagination' => [
                    'current_page' => $messages->currentPage(),
                    'last_page' => $messages->lastPage(),
                    'per_page' => $messages->perPage(),
                    'total' => $messages->total(),
                ],
                'conversation' => [
                    'id' => $conversation->id,
                    'status' => $conversation->status,
                    'last_message_at' => $conversation->last_message_at?->toISOString(),
                ],
            ],
        ]);
    }

    /**
     * Send a message
     */
    public function sendMessage(Request $request, int $conversationId): JsonResponse
    {
        $request->validate([
            'content' => 'required|string|max:2000',
            'message_type' => 'nullable|in:text,image,location,file',
            'attachments' => 'nullable|array',
            'attachments.*' => 'string|max:500',
            'metadata' => 'nullable|array',
        ]);

        $user = Auth::user();
        
        $conversation = ChatConversation::findOrFail($conversationId);
        
        // Check if user is part of this conversation
        if ($conversation->customer_id !== $user->id && $conversation->driver_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access to this conversation',
                'request_id' => $request->header('X-Request-ID', uniqid()),
                'data' => null,
            ], 403);
        }

        // Check if conversation is active
        if ($conversation->status !== 'active') {
            return response()->json([
                'success' => false,
                'message' => 'Cannot send message to inactive conversation',
                'request_id' => $request->header('X-Request-ID', uniqid()),
                'data' => null,
            ], 400);
        }

        DB::beginTransaction();
        try {
            // Create message
            $message = ChatMessage::create([
                'conversation_id' => $conversationId,
                'sender_id' => $user->id,
                'sender_type' => $user->role === 'customer' ? 'customer' : 'driver',
                'message_type' => $request->get('message_type', 'text'),
                'content' => $request->content,
                'attachments' => $request->get('attachments'),
                'metadata' => $request->get('metadata'),
            ]);

            // Load sender relationship
            $message->load('sender');

            // Broadcast the message
            $this->realtimeService->broadcastChatMessage($message, $conversation, $user);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Message sent successfully',
                'request_id' => $request->header('X-Request-ID', uniqid()),
                'data' => [
                    'message' => [
                        'id' => $message->id,
                        'conversation_id' => $message->conversation_id,
                        'sender_id' => $message->sender_id,
                        'sender_type' => $message->sender_type,
                        'message_type' => $message->message_type,
                        'content' => $message->content,
                        'attachments' => $message->attachments,
                        'is_system_message' => $message->is_system_message,
                        'created_at' => $message->created_at->toISOString(),
                        'sender' => [
                            'id' => $message->sender->id,
                            'name' => $message->sender->name,
                            'role' => $message->sender->role,
                        ],
                    ],
                ],
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to send message',
                'request_id' => $request->header('X-Request-ID', uniqid()),
                'data' => null,
            ], 500);
        }
    }

    /**
     * Send system message
     */
    public function sendSystemMessage(Request $request, int $conversationId): JsonResponse
    {
        $request->validate([
            'content' => 'required|string|max:1000',
            'metadata' => 'nullable|array',
        ]);

        $user = Auth::user();
        
        // Only admins or system can send system messages
        if (!$user->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized to send system messages',
                'request_id' => $request->header('X-Request-ID', uniqid()),
                'data' => null,
            ], 403);
        }

        $conversation = ChatConversation::findOrFail($conversationId);

        DB::beginTransaction();
        try {
            // Create system message
            $message = ChatMessage::create([
                'conversation_id' => $conversationId,
                'sender_id' => $user->id,
                'sender_type' => 'system',
                'message_type' => 'system',
                'content' => $request->content,
                'is_system_message' => true,
                'metadata' => $request->get('metadata'),
            ]);

            // Load sender relationship
            $message->load('sender');

            // Broadcast the message
            $this->realtimeService->broadcastChatMessage($message, $conversation, $user);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'System message sent successfully',
                'request_id' => $request->header('X-Request-ID', uniqid()),
                'data' => [
                    'message' => $message->toArray(),
                ],
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to send system message',
                'request_id' => $request->header('X-Request-ID', uniqid()),
                'data' => null,
            ], 500);
        }
    }

    /**
     * End conversation
     */
    public function endConversation(Request $request, int $conversationId): JsonResponse
    {
        $user = Auth::user();
        
        $conversation = ChatConversation::findOrFail($conversationId);
        
        // Check if user is part of this conversation
        if ($conversation->customer_id !== $user->id && $conversation->driver_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access to this conversation',
                'request_id' => $request->header('X-Request-ID', uniqid()),
                'data' => null,
            ], 403);
        }

        $conversation->markAsEnded();

        // Send system message about conversation ending
        $systemMessage = ChatMessage::create([
            'conversation_id' => $conversationId,
            'sender_id' => $user->id,
            'sender_type' => 'system',
            'message_type' => 'system',
            'content' => 'Conversation ended by ' . $user->name,
            'is_system_message' => true,
        ]);

        // Broadcast the system message
        $this->realtimeService->broadcastChatMessage($systemMessage, $conversation, $user);

        return response()->json([
            'success' => true,
            'message' => 'Conversation ended successfully',
            'request_id' => $request->header('X-Request-ID', uniqid()),
            'data' => [
                'conversation_id' => $conversation->id,
                'status' => $conversation->status,
                'ended_at' => $conversation->updated_at->toISOString(),
            ],
        ]);
    }

    /**
     * Get unread message count
     */
    public function unreadCount(Request $request): JsonResponse
    {
        $user = Auth::user();
        
        $count = ChatMessage::whereHas('conversation', function ($query) use ($user) {
                $query->forUser($user->id);
            })
            ->where('sender_id', '!=', $user->id)
            ->whereNull('read_at')
            ->count();

        return response()->json([
            'success' => true,
            'message' => 'Unread message count retrieved successfully',
            'request_id' => $request->header('X-Request-ID', uniqid()),
            'data' => [
                'unread_count' => $count,
            ],
        ]);
    }
}