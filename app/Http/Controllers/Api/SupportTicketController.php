<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SupportTicket;
use App\Models\SupportTicketMessage;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\Rule;

class SupportTicketController extends Controller
{
    /**
     * Get user's support tickets.
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'status' => 'nullable|in:open,in_progress,waiting_customer,waiting_internal,resolved,closed,cancelled',
            'category' => 'nullable|in:order_issue,payment_issue,driver_complaint,app_bug,feature_request,account_issue,refund_request,general_inquiry,other',
            'per_page' => 'nullable|integer|min:1|max:50',
        ]);

        $query = SupportTicket::with(['order', 'assignedAgent'])
            ->where('user_id', auth()->id())
            ->orderBy('created_at', 'desc');

        if ($request->status) {
            $query->where('status', $request->status);
        }

        if ($request->category) {
            $query->where('category', $request->category);
        }

        $tickets = $query->paginate($request->per_page ?? 20);

        return response()->json([
            'success' => true,
            'message' => 'Support tickets retrieved successfully',
            'request_id' => $request->header('X-Request-ID', uniqid()),
            'data' => $tickets->items(),
            'meta' => [
                'total' => $tickets->total(),
                'per_page' => $tickets->perPage(),
                'current_page' => $tickets->currentPage(),
                'last_page' => $tickets->lastPage(),
                'from' => $tickets->firstItem(),
                'to' => $tickets->lastItem(),
                'has_more' => $tickets->hasMorePages(),
            ],
        ]);
    }

    /**
     * Create a new support ticket.
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'subject' => 'required|string|max:255',
            'description' => 'required|string|max:2000',
            'category' => [
                'required',
                Rule::in([
                    'order_issue',
                    'payment_issue',
                    'driver_complaint',
                    'app_bug',
                    'feature_request',
                    'account_issue',
                    'refund_request',
                    'general_inquiry',
                    'other'
                ])
            ],
            'priority' => 'nullable|in:low,medium,high,urgent',
            'order_id' => 'nullable|exists:orders,id',
            'attachments' => 'nullable|array|max:5',
            'attachments.*' => 'url',
            'metadata' => 'nullable|array',
        ]);

        // If order_id is provided, verify user owns the order
        if ($request->order_id) {
            $order = \App\Models\Order::where('id', $request->order_id)
                ->where('user_id', auth()->id())
                ->first();

            if (!$order) {
                return response()->json([
                    'success' => false,
                    'message' => 'Order not found or you do not have permission to reference it',
                    'request_id' => $request->header('X-Request-ID', uniqid()),
                ], 404);
            }
        }

        $ticket = SupportTicket::create([
            'user_id' => auth()->id(),
            'order_id' => $request->order_id,
            'subject' => $request->subject,
            'description' => $request->description,
            'category' => $request->category,
            'priority' => $request->priority ?? 'medium',
            'attachments' => $request->attachments,
            'metadata' => array_merge($request->metadata ?? [], [
                'user_agent' => $request->userAgent(),
                'ip_address' => $request->ip(),
                'created_via' => 'api',
            ]),
        ]);

        // Create initial message
        $ticket->addMessage(
            auth()->user(),
            $request->description,
            $request->attachments ?? []
        );

        $ticket->load(['order', 'assignedAgent', 'messages.user']);

        return response()->json([
            'success' => true,
            'message' => 'Support ticket created successfully',
            'request_id' => $request->header('X-Request-ID', uniqid()),
            'data' => $ticket,
        ], 201);
    }

    /**
     * Get a specific support ticket.
     */
    public function show(SupportTicket $ticket): JsonResponse
    {
        if ($ticket->user_id !== auth()->id()) {
            return response()->json([
                'success' => false,
                'message' => 'You can only view your own support tickets',
                'request_id' => request()->header('X-Request-ID', uniqid()),
            ], 403);
        }

        $ticket->load([
            'order',
            'assignedAgent',
            'publicMessages.user',
            'publicMessages' => function ($query) {
                $query->orderBy('created_at', 'asc');
            }
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Support ticket retrieved successfully',
            'request_id' => request()->header('X-Request-ID', uniqid()),
            'data' => $ticket,
        ]);
    }

    /**
     * Add a message to a support ticket.
     */
    public function addMessage(Request $request, SupportTicket $ticket): JsonResponse
    {
        if ($ticket->user_id !== auth()->id()) {
            return response()->json([
                'success' => false,
                'message' => 'You can only add messages to your own support tickets',
                'request_id' => $request->header('X-Request-ID', uniqid()),
            ], 403);
        }

        if (in_array($ticket->status, ['closed', 'cancelled'])) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot add messages to closed or cancelled tickets',
                'request_id' => $request->header('X-Request-ID', uniqid()),
            ], 400);
        }

        $request->validate([
            'message' => 'required|string|max:2000',
            'attachments' => 'nullable|array|max:5',
            'attachments.*' => 'url',
        ]);

        $message = $ticket->addMessage(
            auth()->user(),
            $request->message,
            $request->attachments ?? []
        );

        // Update ticket status if it was waiting for customer
        if ($ticket->status === 'waiting_customer') {
            $ticket->update(['status' => 'in_progress']);
        }

        $message->load('user');

        return response()->json([
            'success' => true,
            'message' => 'Message added successfully',
            'request_id' => $request->header('X-Request-ID', uniqid()),
            'data' => $message,
        ], 201);
    }

    /**
     * Close a support ticket.
     */
    public function close(SupportTicket $ticket): JsonResponse
    {
        if ($ticket->user_id !== auth()->id()) {
            return response()->json([
                'success' => false,
                'message' => 'You can only close your own support tickets',
                'request_id' => request()->header('X-Request-ID', uniqid()),
            ], 403);
        }

        if (!$ticket->canBeClosed()) {
            return response()->json([
                'success' => false,
                'message' => 'This ticket cannot be closed at this time',
                'request_id' => request()->header('X-Request-ID', uniqid()),
            ], 400);
        }

        $ticket->close();

        // Add system message
        $ticket->messages()->create([
            'user_id' => auth()->id(),
            'message' => 'Ticket closed by customer',
            'is_system' => true,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Support ticket closed successfully',
            'request_id' => request()->header('X-Request-ID', uniqid()),
            'data' => $ticket->fresh(),
        ]);
    }

    /**
     * Rate support ticket satisfaction.
     */
    public function rateSatisfaction(Request $request, SupportTicket $ticket): JsonResponse
    {
        if ($ticket->user_id !== auth()->id()) {
            return response()->json([
                'success' => false,
                'message' => 'You can only rate your own support tickets',
                'request_id' => $request->header('X-Request-ID', uniqid()),
            ], 403);
        }

        if (!in_array($ticket->status, ['resolved', 'closed'])) {
            return response()->json([
                'success' => false,
                'message' => 'You can only rate resolved or closed tickets',
                'request_id' => $request->header('X-Request-ID', uniqid()),
            ], 400);
        }

        $request->validate([
            'satisfaction_rating' => 'required|integer|min:1|max:5',
            'satisfaction_comment' => 'nullable|string|max:500',
        ]);

        $ticket->update([
            'satisfaction_rating' => $request->satisfaction_rating,
            'satisfaction_comment' => $request->satisfaction_comment,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Satisfaction rating submitted successfully',
            'request_id' => $request->header('X-Request-ID', uniqid()),
            'data' => $ticket->fresh(),
        ]);
    }

    /**
     * Get support ticket statistics for user.
     */
    public function statistics(): JsonResponse
    {
        $userId = auth()->id();

        $stats = [
            'total_tickets' => SupportTicket::where('user_id', $userId)->count(),
            'open_tickets' => SupportTicket::where('user_id', $userId)->open()->count(),
            'resolved_tickets' => SupportTicket::where('user_id', $userId)->where('status', 'resolved')->count(),
            'closed_tickets' => SupportTicket::where('user_id', $userId)->where('status', 'closed')->count(),
            'average_satisfaction' => SupportTicket::where('user_id', $userId)
                ->whereNotNull('satisfaction_rating')
                ->avg('satisfaction_rating'),
            'tickets_by_category' => SupportTicket::where('user_id', $userId)
                ->selectRaw('category, COUNT(*) as count')
                ->groupBy('category')
                ->pluck('count', 'category'),
        ];

        return response()->json([
            'success' => true,
            'message' => 'Support statistics retrieved successfully',
            'request_id' => request()->header('X-Request-ID', uniqid()),
            'data' => $stats,
        ]);
    }
}