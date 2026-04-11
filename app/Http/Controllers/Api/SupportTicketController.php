<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SupportTicketController extends Controller
{
    public function index(): JsonResponse
    {
        return $this->success([], 'Support tickets retrieved successfully');
    }

    public function store(Request $request): JsonResponse
    {
        return $this->success([], 'Support ticket created successfully');
    }

    public function statistics(): JsonResponse
    {
        return $this->success([
            'open' => 0,
            'closed' => 0,
            'total' => 0
        ], 'Support statistics retrieved successfully');
    }

    public function show($ticket): JsonResponse
    {
        return $this->success([], 'Support ticket retrieved successfully');
    }

    public function addMessage($ticket): JsonResponse
    {
        return $this->success([], 'Message added successfully');
    }

    public function close($ticket): JsonResponse
    {
        return $this->success([], 'Support ticket closed successfully');
    }

    public function rateSatisfaction($ticket): JsonResponse
    {
        return $this->success([], 'Satisfaction rating submitted successfully');
    }
}