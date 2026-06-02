<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CustomerSatisfactionSurvey;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FeedbackController extends Controller
{
    /**
     * Store customer satisfaction survey response.
     */
    public function customerSatisfaction(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'rating'                 => 'required|integer|min:1|max:5',
            'feedback'               => 'nullable|string|max:2000',
            'completed_orders_count' => 'nullable|integer|min:0',
            'source'                 => 'nullable|string|max:64',
        ]);

        $survey = CustomerSatisfactionSurvey::create([
            'user_id'                => $request->user()->id,
            'rating'                 => $validated['rating'],
            'feedback'               => $validated['feedback'] ?? null,
            'completed_orders_count' => $validated['completed_orders_count'] ?? 0,
            'source'                 => $validated['source'] ?? 'customer_app',
        ]);

        return $this->created($survey, 'Thank you for your feedback');
    }
}
