<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChatController extends Controller
{
    public function conversations(): JsonResponse
    {
        return $this->success([], 'Conversations retrieved successfully');
    }

    public function getOrCreateConversation($order): JsonResponse
    {
        return $this->success([], 'Conversation retrieved successfully');
    }

    public function messages($conversation): JsonResponse
    {
        return $this->success([], 'Messages retrieved successfully');
    }

    public function sendMessage($conversation): JsonResponse
    {
        return $this->success([], 'Message sent successfully');
    }

    public function sendSystemMessage($conversation): JsonResponse
    {
        return $this->success([], 'System message sent successfully');
    }

    public function endConversation($conversation): JsonResponse
    {
        return $this->success([], 'Conversation ended successfully');
    }

    public function unreadCount(): JsonResponse
    {
        return $this->success(['count' => 0], 'Unread count retrieved successfully');
    }
}