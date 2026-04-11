<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DocumentSubmissionController extends Controller
{
    /**
     * @OA\Post(
     *     path="/api/v1/documents/submit",
     *     summary="Submit document for verification",
     *     tags={"Documents"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(response=201, description="Document submitted successfully")
     * )
     */
    public function submit(Request $request): JsonResponse
    {
        return $this->success([], 'Document submitted successfully');
    }

    /**
     * @OA\Get(
     *     path="/api/v1/documents/my-documents",
     *     summary="Get current user's documents",
     *     tags={"Documents"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(response=200, description="User documents retrieved")
     * )
     */
    public function myDocuments(Request $request): JsonResponse
    {
        return $this->success([], 'Documents retrieved successfully');
    }

    /**
     * @OA\Get(
     *     path="/api/v1/documents/types",
     *     summary="Get available document types",
     *     tags={"Documents"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(response=200, description="Document types retrieved")
     * )
     */
    public function documentTypes(): JsonResponse
    {
        return $this->success([
            ['id' => 1, 'name' => 'Driver License', 'required' => true],
            ['id' => 2, 'name' => 'Vehicle Registration', 'required' => true],
            ['id' => 3, 'name' => 'Insurance Certificate', 'required' => false]
        ], 'Document types retrieved successfully');
    }

    /**
     * @OA\Get(
     *     path="/api/v1/documents/verification-status",
     *     summary="Get verification status",
     *     tags={"Documents"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(response=200, description="Verification status retrieved")
     * )
     */
    public function verificationStatus(Request $request): JsonResponse
    {
        return $this->success([
            'status' => 'pending',
            'verified_documents' => 0,
            'total_documents' => 0
        ], 'Verification status retrieved successfully');
    }

    /**
     * @OA\Get(
     *     path="/api/v1/documents/{document}",
     *     summary="Get document details",
     *     tags={"Documents"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(response=200, description="Document details retrieved")
     * )
     */
    public function show($document): JsonResponse
    {
        return $this->success([], 'Document details retrieved successfully');
    }

    /**
     * @OA\Post(
     *     path="/api/v1/documents/{document}/resubmit",
     *     summary="Resubmit document",
     *     tags={"Documents"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(response=200, description="Document resubmitted")
     * )
     */
    public function resubmit($document): JsonResponse
    {
        return $this->success([], 'Document resubmitted successfully');
    }
}