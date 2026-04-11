<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class SimpleTestController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/v1/simple-test",
     *     summary="Simple test endpoint",
     *     tags={"Test"},
     *     @OA\Response(
     *         response=200,
     *         description="Success",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Test successful")
     *         )
     *     )
     * )
     */
    public function test(): JsonResponse
    {
        return $this->success(['message' => 'Test successful']);
    }
}