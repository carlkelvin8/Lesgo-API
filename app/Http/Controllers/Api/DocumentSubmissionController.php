<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DocumentVerification;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\Rule;

class DocumentSubmissionController extends Controller
{
    /**
     * Submit documents for verification.
     */
    public function submit(Request $request): JsonResponse
    {
        $request->validate([
            'document_type' => [
                'required',
                'string',
                Rule::in(array_keys(DocumentVerification::getDocumentTypes()))
            ],
            'document_number' => 'nullable|string|max:100',
            'document_urls' => 'required|array|min:1|max:5',
            'document_urls.*' => 'url|max:500',
            'description' => 'nullable|string|max:500',
            'expires_at' => 'nullable|date|after:today',
            'metadata' => 'nullable|array',
        ]);

        // Check if user already has a pending/under review document of this type
        $existingDocument = DocumentVerification::where('user_id', auth()->id())
            ->where('document_type', $request->document_type)
            ->whereIn('status', ['pending', 'under_review'])
            ->first();

        if ($existingDocument) {
            return response()->json([
                'success' => false,
                'message' => 'You already have a pending verification for this document type',
                'request_id' => $request->header('X-Request-ID', uniqid()),
                'data' => [
                    'existing_document' => $existingDocument,
                ],
            ], 400);
        }

        $document = DocumentVerification::create([
            'user_id' => auth()->id(),
            'document_type' => $request->document_type,
            'document_number' => $request->document_number,
            'document_urls' => $request->document_urls,
            'description' => $request->description,
            'expires_at' => $request->expires_at,
            'metadata' => $request->metadata ?? [],
            'submitted_at' => now(),
        ]);

        $document->load(['user']);

        // TODO: Send notification to admins about new document submission
        // $this->notifyAdmins('new_document_submission', $document);

        return response()->json([
            'success' => true,
            'message' => 'Document submitted successfully for verification',
            'request_id' => $request->header('X-Request-ID', uniqid()),
            'data' => $document,
        ], 201);
    }

    /**
     * Get user's submitted documents.
     */
    public function myDocuments(Request $request): JsonResponse
    {
        $request->validate([
            'status' => 'nullable|in:pending,under_review,approved,rejected,expired',
            'document_type' => 'nullable|string',
            'per_page' => 'nullable|integer|min:1|max:50',
        ]);

        $query = DocumentVerification::where('user_id', auth()->id())
            ->with(['verifiedBy'])
            ->when($request->status, fn($q) => $q->where('status', $request->status))
            ->when($request->document_type, fn($q) => $q->where('document_type', $request->document_type))
            ->orderBy('submitted_at', 'desc');

        $documents = $query->paginate($request->per_page ?? 20);

        return response()->json([
            'success' => true,
            'message' => 'Your documents retrieved successfully',
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
            'summary' => [
                'pending' => DocumentVerification::where('user_id', auth()->id())->pending()->count(),
                'approved' => DocumentVerification::where('user_id', auth()->id())->approved()->count(),
                'rejected' => DocumentVerification::where('user_id', auth()->id())->rejected()->count(),
                'expiring_soon' => DocumentVerification::where('user_id', auth()->id())
                    ->approved()
                    ->whereNotNull('expires_at')
                    ->where('expires_at', '<=', now()->addDays(30))
                    ->where('expires_at', '>', now())
                    ->count(),
            ],
        ]);
    }

    /**
     * Get specific document details.
     */
    public function show(DocumentVerification $document): JsonResponse
    {
        // Ensure user can only view their own documents
        if ($document->user_id !== auth()->id()) {
            return response()->json([
                'success' => false,
                'message' => 'You can only view your own documents',
                'request_id' => request()->header('X-Request-ID', uniqid()),
            ], 403);
        }

        $document->load(['verifiedBy']);

        return response()->json([
            'success' => true,
            'message' => 'Document details retrieved successfully',
            'request_id' => request()->header('X-Request-ID', uniqid()),
            'data' => $document,
        ]);
    }

    /**
     * Resubmit a rejected or expired document.
     */
    public function resubmit(Request $request, DocumentVerification $document): JsonResponse
    {
        // Ensure user owns the document
        if ($document->user_id !== auth()->id()) {
            return response()->json([
                'success' => false,
                'message' => 'You can only resubmit your own documents',
                'request_id' => $request->header('X-Request-ID', uniqid()),
            ], 403);
        }

        if (!$document->canBeResubmitted()) {
            return response()->json([
                'success' => false,
                'message' => 'This document cannot be resubmitted in its current status',
                'request_id' => $request->header('X-Request-ID', uniqid()),
            ], 400);
        }

        $request->validate([
            'document_number' => 'nullable|string|max:100',
            'document_urls' => 'required|array|min:1|max:5',
            'document_urls.*' => 'url|max:500',
            'description' => 'nullable|string|max:500',
            'expires_at' => 'nullable|date|after:today',
            'metadata' => 'nullable|array',
        ]);

        $document->update([
            'document_number' => $request->document_number,
            'document_urls' => $request->document_urls,
            'description' => $request->description,
            'expires_at' => $request->expires_at,
            'metadata' => $request->metadata ?? [],
            'status' => 'pending',
            'rejection_reason' => null,
            'admin_notes' => null,
            'verified_by' => null,
            'reviewed_at' => null,
            'submitted_at' => now(),
        ]);

        $document->incrementAttempts();

        return response()->json([
            'success' => true,
            'message' => 'Document resubmitted successfully',
            'request_id' => $request->header('X-Request-ID', uniqid()),
            'data' => $document->fresh(['verifiedBy']),
        ]);
    }

    /**
     * Get available document types and their requirements.
     */
    public function documentTypes(): JsonResponse
    {
        $documentTypes = DocumentVerification::getDocumentTypes();
        
        // Add requirements for each document type
        $requirements = [
            'driver_license' => [
                'description' => 'Valid driver\'s license issued by LTO',
                'required_fields' => ['document_number', 'expires_at'],
                'accepted_formats' => ['JPG', 'PNG', 'PDF'],
                'max_file_size' => '5MB',
                'notes' => 'Both front and back sides required',
            ],
            'vehicle_registration' => [
                'description' => 'Official Receipt (OR) and Certificate of Registration (CR)',
                'required_fields' => ['document_number'],
                'accepted_formats' => ['JPG', 'PNG', 'PDF'],
                'max_file_size' => '5MB',
                'notes' => 'Both OR and CR documents required',
            ],
            'vehicle_insurance' => [
                'description' => 'Valid vehicle insurance policy',
                'required_fields' => ['document_number', 'expires_at'],
                'accepted_formats' => ['JPG', 'PNG', 'PDF'],
                'max_file_size' => '5MB',
                'notes' => 'Must be current and valid',
            ],
            'business_permit' => [
                'description' => 'Valid business permit from local government',
                'required_fields' => ['document_number', 'expires_at'],
                'accepted_formats' => ['JPG', 'PNG', 'PDF'],
                'max_file_size' => '5MB',
                'notes' => 'Must be current and valid',
            ],
            'valid_id' => [
                'description' => 'Government-issued valid ID',
                'required_fields' => ['document_number'],
                'accepted_formats' => ['JPG', 'PNG'],
                'max_file_size' => '5MB',
                'notes' => 'Accepted IDs: Passport, Driver\'s License, SSS, PhilHealth, etc.',
            ],
        ];

        $result = [];
        foreach ($documentTypes as $key => $name) {
            $result[$key] = [
                'name' => $name,
                'requirements' => $requirements[$key] ?? [
                    'description' => 'Document verification required',
                    'required_fields' => [],
                    'accepted_formats' => ['JPG', 'PNG', 'PDF'],
                    'max_file_size' => '5MB',
                    'notes' => 'Please ensure document is clear and readable',
                ],
            ];
        }

        return response()->json([
            'success' => true,
            'message' => 'Document types and requirements retrieved successfully',
            'request_id' => request()->header('X-Request-ID', uniqid()),
            'data' => $result,
        ]);
    }

    /**
     * Get document verification status summary for user.
     */
    public function verificationStatus(): JsonResponse
    {
        $userId = auth()->id();
        
        $status = [
            'overall_status' => 'incomplete', // incomplete, pending, verified, issues
            'required_documents' => [],
            'submitted_documents' => [],
            'verification_progress' => 0,
        ];

        // Define required documents based on user role
        $user = auth()->user();
        $requiredDocs = [];
        
        if ($user->isDriver()) {
            $requiredDocs = ['driver_license', 'vehicle_registration', 'vehicle_insurance', 'valid_id'];
        } elseif ($user->isPartnerAdmin()) {
            $requiredDocs = ['business_permit', 'bir_certificate', 'valid_id'];
        } else {
            $requiredDocs = ['valid_id'];
        }

        $submittedDocs = DocumentVerification::where('user_id', $userId)
            ->get()
            ->groupBy('document_type');

        foreach ($requiredDocs as $docType) {
            $docName = DocumentVerification::getDocumentTypes()[$docType] ?? $docType;
            
            if (isset($submittedDocs[$docType])) {
                $latestDoc = $submittedDocs[$docType]->sortByDesc('submitted_at')->first();
                $status['submitted_documents'][] = [
                    'document_type' => $docType,
                    'document_name' => $docName,
                    'status' => $latestDoc->status,
                    'submitted_at' => $latestDoc->submitted_at,
                    'reviewed_at' => $latestDoc->reviewed_at,
                ];
            } else {
                $status['required_documents'][] = [
                    'document_type' => $docType,
                    'document_name' => $docName,
                    'status' => 'not_submitted',
                ];
            }
        }

        // Calculate progress
        $totalRequired = count($requiredDocs);
        $approved = collect($status['submitted_documents'])->where('status', 'approved')->count();
        $status['verification_progress'] = $totalRequired > 0 ? round(($approved / $totalRequired) * 100) : 0;

        // Determine overall status
        if ($approved === $totalRequired) {
            $status['overall_status'] = 'verified';
        } elseif (collect($status['submitted_documents'])->whereIn('status', ['pending', 'under_review'])->count() > 0) {
            $status['overall_status'] = 'pending';
        } elseif (collect($status['submitted_documents'])->where('status', 'rejected')->count() > 0) {
            $status['overall_status'] = 'issues';
        }

        return response()->json([
            'success' => true,
            'message' => 'Verification status retrieved successfully',
            'request_id' => request()->header('X-Request-ID', uniqid()),
            'data' => $status,
        ]);
    }
}