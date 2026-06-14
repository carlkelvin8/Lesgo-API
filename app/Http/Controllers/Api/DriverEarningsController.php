<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\DriverEarningsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class DriverEarningsController extends Controller
{
    public function __construct(
        private DriverEarningsService $earningsService,
    ) {}

    /**
     * Driver earnings summary and trip breakdown.
     */
    public function myEarnings(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'period' => 'nullable|in:today,week,month,all',
        ]);

        try {
            $data = $this->earningsService->getEarnings(
                $request->user(),
                $validated['period'] ?? 'today'
            );
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => collect($e->errors())->flatten()->first()
                    ?? 'Unable to load earnings.',
                'errors' => $e->errors(),
            ], 422);
        }

        return $this->success($data, 'Driver earnings retrieved successfully');
    }
}
