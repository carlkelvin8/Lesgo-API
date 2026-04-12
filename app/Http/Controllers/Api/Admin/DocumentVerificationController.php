<?php

namespace App\Http\Controllers\Api\Admin;

use App\Models\DocumentVerification;
use App\Models\User;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DocumentVerificationController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
        $this->middleware(function ($request, $next) {
            if (!$request->user() || !$request->user()->isAdmin()) {
                return response()->json(['success' => false, 'message' => 'Forbidden'], 403);
            }
            return $next($request);
        });
    }

    /**
     * Get all document submissions with filtering.
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'status' => 'nullable|in:pending,approved,rejected,expired,under_review',
            'document_type' => 'nullable|in:drivers_license,vehicle_registration,insurance,barangay_clearance,ncr_clearance',
            'user_id' => 'nullable|integer',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $query = DocumentVerification::with([
            'user:id,name,email,phone_number',
            'verifiedBy:id,name,email',
        ]);

        // Filter by status
        if (!empty($validated['status'])) {
            $query->where('status', $validated['status']);
        }

        // Filter by document type
        if (!empty($validated['document_type'])) {
            $query->where('document_type', $validated['document_type']);
        }

        // Filter by user
        if (!empty($validated['user_id'])) {
            $query->where('user_id', $validated['user_id']);
        }

        $perPage = (int) ($validated['per_page'] ?? 20);
        $documents = $query->orderByDesc('submitted_at')->paginate($perPage);

        return $this->success($documents, 'Document submissions retrieved successfully');
    }

    /**
     * Get document verification statistics.
     */
    public function statistics(): JsonResponse
    {
        $stats = [
            'pending' => DocumentVerification::where('status', 'pending')->count(),
            'under_review' => DocumentVerification::where('status', 'under_review')->count(),
            'approved' => DocumentVerification::where('status', 'approved')->count(),
            'rejected' => DocumentVerification::where('status', 'rejected')->count(),
            'expired' => DocumentVerification::where('status', 'expired')->count(),
            'total' => DocumentVerification::count(),
        ];

        // Average review time
        $avgReviewTime = DocumentVerification::whereNotNull('reviewed_at')
            ->whereNotNull('submitted_at')
            ->get()
            ->avg(function ($doc) {
                return $doc->submitted_at->diffInHours($doc->reviewed_at);
            });

        $stats['average_review_time_hours'] = $avgReviewTime ? round($avgReviewTime, 2) : null;
        $stats['approval_rate'] = $stats['total'] > 0 
            ? round(($stats['approved'] / $stats['total']) * 100, 2) 
            : 0;

        return $this->success($stats, 'Statistics retrieved successfully');
    }

    /**
     * Get users with pending documents.
     */
    public function usersWithPendingDocuments(): JsonResponse
    {
        $users = User::whereHas('documents', function ($query) {
            $query->where('status', 'pending');
        })
            ->withCount(['documents' => function ($query) {
                $query->where('status', 'pending');
            }])
            ->with(['documents' => function ($query) {
                $query->where('status', 'pending')
                      ->select('id', 'user_id', 'document_type', 'submitted_at');
            }])
            ->select('id', 'name', 'email', 'phone_number')
            ->orderByDesc('documents_count')
            ->get();

        return $this->success($users->map(function ($user) {
            return [
                'user_id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone_number' => $user->phone_number,
                'pending_documents' => $user->documents_count,
                'documents' => $user->documents->map(function ($doc) {
                    return [
                        'id' => $doc->id,
                        'document_type' => $doc->document_type,
                        'submitted_at' => $doc->submitted_at->toISOString(),
                    ];
                }),
            ];
        }), 'Users with pending documents retrieved successfully');
    }

    /**
     * Bulk approve documents.
     */
    public function bulkApprove(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'document_ids' => 'required|array|min:1',
            'document_ids.*' => 'required|integer|exists:document_verifications,id',
            'notes' => 'nullable|string|max:1000',
        ]);

        $user = $request->user();
        $approved = 0;
        $failed = 0;
        $errors = [];

        foreach ($validated['document_ids'] as $docId) {
            $document = DocumentVerification::find($docId);

            if (!$document) {
                $failed++;
                $errors[] = "Document #{$docId} not found";
                continue;
            }

            if ($document->status !== 'pending' && $document->status !== 'under_review') {
                $failed++;
                $errors[] = "Document #{$docId} is not in pending/under_review status";
                continue;
            }

            $document->update([
                'status' => 'approved',
                'verified_by' => $user->id,
                'reviewed_at' => now(),
                'admin_notes' => $validated['notes'] ?? null,
                'rejection_reason' => null,
            ]);

            $approved++;
        }

        return $this->success([
            'approved_count' => $approved,
            'failed_count' => $failed,
            'errors' => $errors,
        ], 'Documents bulk approved successfully');
    }

    /**
     * Get user's document verification history.
     */
    public function userHistory($userId): JsonResponse
    {
        $user = User::find($userId);

        if (!$user) {
            return $this->error('User not found.', 404);
        }

        $documents = $user->documents()
            ->with('verifiedBy:id,name,email')
            ->orderByDesc('submitted_at')
            ->get();

        return $this->success([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ],
            'documents' => $documents->map(function ($doc) {
                return [
                    'id' => $doc->id,
                    'document_type' => $doc->document_type,
                    'document_number' => $doc->document_number,
                    'status' => $doc->status,
                    'submitted_at' => $doc->submitted_at?->toISOString(),
                    'reviewed_at' => $doc->reviewed_at?->toISOString(),
                    'verified_by' => $doc->verifiedBy?->name,
                    'rejection_reason' => $doc->rejection_reason,
                    'admin_notes' => $doc->admin_notes,
                    'expires_at' => $doc->expires_at?->toISOString(),
                ];
            }),
        ], 'User document history retrieved successfully');
    }

    /**
     * Get document details (admin view).
     */
    public function show($documentId): JsonResponse
    {
        $document = DocumentVerification::with([
            'user:id,name,email,phone_number',
            'verifiedBy:id,name,email',
        ])->find($documentId);

        if (!$document) {
            return $this->error('Document not found.', 404);
        }

        return $this->success($document, 'Document details retrieved successfully');
    }

    /**
     * Start document review process.
     */
    public function startReview($documentId): JsonResponse
    {
        $document = DocumentVerification::find($documentId);

        if (!$document) {
            return $this->error('Document not found.', 404);
        }

        if ($document->status !== 'pending') {
            return $this->error('Can only start review on pending documents.', 400);
        }

        $document->update([
            'status' => 'under_review',
        ]);

        return $this->success($document, 'Document review started successfully');
    }

    /**
     * Approve a document.
     */
    public function approve(Request $request, $documentId): JsonResponse
    {
        $validated = $request->validate([
            'notes' => 'nullable|string|max:1000',
            'expires_at' => 'nullable|date|after:today',
        ]);

        $document = DocumentVerification::find($documentId);

        if (!$document) {
            return $this->error('Document not found.', 404);
        }

        if ($document->status !== 'pending' && $document->status !== 'under_review') {
            return $this->error('Can only approve pending or under_review documents.', 400);
        }

        $user = $request->user();

        $document->update([
            'status' => 'approved',
            'verified_by' => $user->id,
            'reviewed_at' => now(),
            'admin_notes' => $validated['notes'] ?? null,
            'rejection_reason' => null,
            'expires_at' => $validated['expires_at'] ?? $document->expires_at,
        ]);

        // If this is a driver's license, update driver profile
        if ($document->document_type === 'drivers_license' && $document->user->driverProfile) {
            $document->user->driverProfile->update([
                'license_number' => $document->document_number,
                'license_expiry_date' => $document->expires_at,
            ]);
        }

        $document->load(['user:id,name,email', 'verifiedBy:id,name']);

        return $this->success($document, 'Document approved successfully');
    }

    /**
     * Reject a document.
     */
    public function reject(Request $request, $documentId): JsonResponse
    {
        $validated = $request->validate([
            'rejection_reason' => 'required|string|max:500',
            'notes' => 'nullable|string|max:1000',
        ]);

        $document = DocumentVerification::find($documentId);

        if (!$document) {
            return $this->error('Document not found.', 404);
        }

        if ($document->status !== 'pending' && $document->status !== 'under_review') {
            return $this->error('Can only reject pending or under_review documents.', 400);
        }

        $user = $request->user();

        $document->update([
            'status' => 'rejected',
            'verified_by' => $user->id,
            'reviewed_at' => now(),
            'rejection_reason' => $validated['rejection_reason'],
            'admin_notes' => $validated['notes'] ?? null,
        ]);

        $document->load(['user:id,name,email', 'verifiedBy:id,name']);

        return $this->success($document, 'Document rejected successfully');
    }
}
