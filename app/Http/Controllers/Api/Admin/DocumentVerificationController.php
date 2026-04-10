<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\DocumentVerification;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\Rule;

class DocumentVerificationController extends Controller
{
    public function __construct()
    {
        // Only admin users can access these endpoints
        $this->middleware(function ($request, $next) {
            $user = auth()->user();
            if (!$user || $user->role !== 'admin') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized. Admin access required.',
                    'request_id' => $request->header('X-Request-ID', uniqid()),
                ], 403);
            }
            return $next($request);
        });
    }

    /**
     * Get all pending document verifications for admin review.
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'status' => 'nullable|in:pending,under_review,approved,rejected,expired',
            'document_type' => 'nullable|string',
            'user_id' => 'nullable|exists:users,id',
            'per_page' => 'nullable|integer|min:1|max:100',
            'sort_by' => 'nullable|in:submitted_at,reviewed_at,user_name,document_type,status',
            'sort_order' => 'nullable|in:asc,desc',
        ]);

        $query = DocumentVerification::with(['user', 'verifiedBy'])
            ->when($request->status, fn($q) => $q->where('status', $request->status))
            ->when($request->document_type, fn($q) => $q->where('document_type', $request->document_type))
            ->when($request->user_id, fn($q) => $q->where('user_id', $request->user_id));

        // Default sorting
        $sortBy = $request->sort_by ?? 'submitted_at';
        $sortOrder = $request->sort_order ?? 'desc';

        if ($sortBy === 'user_name') {
            $query->join('users', 'document_verifications.user_id', '=', 'users.id')
                  ->orderBy('users.name', $sortOrder)
                  ->select('document_verifications.*');
        } else {
            $query->orderBy($sortBy, $sortOrder);
        }

        $documents = $query->paginate($request->per_page ?? 20);

        return response()->json([
            'success' => true,
            'message' => 'Document verifications retrieved successfully',
            'request_id' => $request->header('X-Request-ID', uniqid()),
            'data' => $documents->items(),
            'meta' => [
                'total' => $documents->total(),
                'per_page' => $documents->perPage(),
                'current_page' => $documents->currentPage(),
                'last_page' => $documents->lastPage(),
                'from' => $documents->firstItem(),
                'to' => $documents->lastItem(),
                'has_more' => $documents->hasMorePages(),
            ],
            'filters' => [
                'available_statuses' => DocumentVerification::getStatusLabels(),
                'available_document_types' => DocumentVerification::getDocumentTypes(),
            ],
        ]);
    }

    /**
     * Get detailed information about a specific document verification.
     */
    public function show(DocumentVerification $document): JsonResponse
    {
        $document->load(['user', 'verifiedBy']);

        return response()->json([
            'success' => true,
            'message' => 'Document verification details retrieved successfully',
            'request_id' => request()->header('X-Request-ID', uniqid()),
            'data' => $document,
        ]);
    }

    /**
     * Mark document as under review.
     */
    public function startReview(Request $request, DocumentVerification $document): JsonResponse
    {
        if (!$document->canBeReviewed()) {
            return response()->json([
                'success' => false,
                'message' => 'Document cannot be reviewed in its current status',
                'request_id' => $request->header('X-Request-ID', uniqid()),
            ], 400);
        }

        $document->markAsUnderReview(auth()->user());

        return response()->json([
            'success' => true,
            'message' => 'Document marked as under review',
            'request_id' => $request->header('X-Request-ID', uniqid()),
            'data' => $document->fresh(['user', 'verifiedBy']),
        ]);
    }

    /**
     * Approve a document verification.
     */
    public function approve(Request $request, DocumentVerification $document): JsonResponse
    {
        $request->validate([
            'admin_notes' => 'nullable|string|max:1000',
            'expires_at' => 'nullable|date|after:today',
        ]);

        if (!$document->canBeReviewed()) {
            return response()->json([
                'success' => false,
                'message' => 'Document cannot be approved in its current status',
                'request_id' => $request->header('X-Request-ID', uniqid()),
            ], 400);
        }

        $document->approve(auth()->user(), $request->admin_notes);

        // Set expiration date if provided
        if ($request->expires_at) {
            $document->update(['expires_at' => $request->expires_at]);
        }

        // TODO: Send notification to user about approval
        // $this->notifyUser($document->user, 'document_approved', $document);

        return response()->json([
            'success' => true,
            'message' => 'Document approved successfully',
            'request_id' => $request->header('X-Request-ID', uniqid()),
            'data' => $document->fresh(['user', 'verifiedBy']),
        ]);
    }

    /**
     * Reject a document verification.
     */
    public function reject(Request $request, DocumentVerification $document): JsonResponse
    {
        $request->validate([
            'rejection_reason' => 'required|string|max:500',
            'admin_notes' => 'nullable|string|max:1000',
        ]);

        if (!$document->canBeReviewed()) {
            return response()->json([
                'success' => false,
                'message' => 'Document cannot be rejected in its current status',
                'request_id' => $request->header('X-Request-ID', uniqid()),
            ], 400);
        }

        $document->reject(
            auth()->user(),
            $request->rejection_reason,
            $request->admin_notes
        );

        // TODO: Send notification to user about rejection
        // $this->notifyUser($document->user, 'document_rejected', $document);

        return response()->json([
            'success' => true,
            'message' => 'Document rejected successfully',
            'request_id' => $request->header('X-Request-ID', uniqid()),
            'data' => $document->fresh(['user', 'verifiedBy']),
        ]);
    }

    /**
     * Get document verification statistics for admin dashboard.
     */
    public function statistics(): JsonResponse
    {
        $stats = [
            'total_documents' => DocumentVerification::count(),
            'pending_review' => DocumentVerification::pending()->count(),
            'under_review' => DocumentVerification::underReview()->count(),
            'approved_today' => DocumentVerification::approved()
                ->whereDate('reviewed_at', today())
                ->count(),
            'rejected_today' => DocumentVerification::rejected()
                ->whereDate('reviewed_at', today())
                ->count(),
            'expiring_soon' => DocumentVerification::approved()
                ->whereNotNull('expires_at')
                ->where('expires_at', '<=', now()->addDays(30))
                ->where('expires_at', '>', now())
                ->count(),
            'expired' => DocumentVerification::expired()->count(),
            
            // By document type
            'by_document_type' => DocumentVerification::selectRaw('document_type, COUNT(*) as count')
                ->groupBy('document_type')
                ->pluck('count', 'document_type'),
            
            // By status
            'by_status' => DocumentVerification::selectRaw('status, COUNT(*) as count')
                ->groupBy('status')
                ->pluck('count', 'status'),
            
            // Recent activity (last 7 days)
            'recent_submissions' => DocumentVerification::where('submitted_at', '>=', now()->subDays(7))
                ->count(),
            'recent_reviews' => DocumentVerification::whereNotNull('reviewed_at')
                ->where('reviewed_at', '>=', now()->subDays(7))
                ->count(),
        ];

        return response()->json([
            'success' => true,
            'message' => 'Document verification statistics retrieved successfully',
            'request_id' => request()->header('X-Request-ID', uniqid()),
            'data' => $stats,
        ]);
    }

    /**
     * Get users with pending document verifications.
     */
    public function usersWithPendingDocuments(Request $request): JsonResponse
    {
        $request->validate([
            'per_page' => 'nullable|integer|min:1|max:50',
        ]);

        $users = User::whereHas('documentVerifications', function ($query) {
                $query->whereIn('status', ['pending', 'under_review']);
            })
            ->withCount([
                'documentVerifications as pending_documents' => function ($query) {
                    $query->where('status', 'pending');
                },
                'documentVerifications as under_review_documents' => function ($query) {
                    $query->where('status', 'under_review');
                }
            ])
            ->with(['documentVerifications' => function ($query) {
                $query->whereIn('status', ['pending', 'under_review'])
                      ->orderBy('submitted_at', 'desc');
            }])
            ->paginate($request->per_page ?? 20);

        return response()->json([
            'success' => true,
            'message' => 'Users with pending documents retrieved successfully',
            'request_id' => $request->header('X-Request-ID', uniqid()),
            'data' => $users->items(),
            'meta' => [
                'total' => $users->total(),
                'per_page' => $users->perPage(),
                'current_page' => $users->currentPage(),
                'last_page' => $users->lastPage(),
                'from' => $users->firstItem(),
                'to' => $users->lastItem(),
                'has_more' => $users->hasMorePages(),
            ],
        ]);
    }

    /**
     * Bulk approve multiple documents.
     */
    public function bulkApprove(Request $request): JsonResponse
    {
        $request->validate([
            'document_ids' => 'required|array|min:1|max:50',
            'document_ids.*' => 'integer|exists:document_verifications,id',
            'admin_notes' => 'nullable|string|max:1000',
        ]);

        $documents = DocumentVerification::whereIn('id', $request->document_ids)
            ->where(function ($query) {
                $query->where('status', 'pending')
                      ->orWhere('status', 'under_review');
            })
            ->get();

        if ($documents->count() !== count($request->document_ids)) {
            return response()->json([
                'success' => false,
                'message' => 'Some documents cannot be approved or do not exist',
                'request_id' => $request->header('X-Request-ID', uniqid()),
            ], 400);
        }

        $approved = 0;
        foreach ($documents as $document) {
            $document->approve(auth()->user(), $request->admin_notes);
            $approved++;
        }

        return response()->json([
            'success' => true,
            'message' => "Successfully approved {$approved} documents",
            'request_id' => $request->header('X-Request-ID', uniqid()),
            'data' => [
                'approved_count' => $approved,
                'document_ids' => $request->document_ids,
            ],
        ]);
    }

    /**
     * Get document verification history for a specific user.
     */
    public function userHistory(Request $request, User $user): JsonResponse
    {
        $request->validate([
            'document_type' => 'nullable|string',
            'per_page' => 'nullable|integer|min:1|max:50',
        ]);

        $query = $user->documentVerifications()
            ->with(['verifiedBy'])
            ->when($request->document_type, fn($q) => $q->where('document_type', $request->document_type))
            ->orderBy('submitted_at', 'desc');

        $documents = $query->paginate($request->per_page ?? 20);

        return response()->json([
            'success' => true,
            'message' => 'User document verification history retrieved successfully',
            'request_id' => $request->header('X-Request-ID', uniqid()),
            'data' => [
                'user' => $user->only(['id', 'name', 'email', 'role']),
                'documents' => $documents->items(),
            ],
            'meta' => [
                'total' => $documents->total(),
                'per_page' => $documents->perPage(),
                'current_page' => $documents->currentPage(),
                'last_page' => $documents->lastPage(),
                'from' => $documents->firstItem(),
                'to' => $documents->lastItem(),
                'has_more' => $documents->hasMorePages(),
            ],
        ]);
    }
}