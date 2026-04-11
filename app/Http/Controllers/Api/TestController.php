<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class TestController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/v1/test",
     *     summary="Test endpoint",
     *     tags={"Test"},
     *     @OA\Response(
     *         response=200,
     *         description="Test response",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Test successful")
     *         )
     *     )
     * )
     */
    public function test(): JsonResponse
    {
        return $this->success(null, 'Test successful');
    }
}