<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSupportTicketRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return auth()->check();
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'subject' => 'required|string|max:255',
            'description' => 'required|string|min:10|max:2000',
            'category' => [
                'required',
                Rule::in([
                    'order_issue',
                    'payment_issue',
                    'driver_complaint',
                    'app_bug',
                    'feature_request',
                    'account_issue',
                    'refund_request',
                    'general_inquiry',
                    'other'
                ])
            ],
            'priority' => 'nullable|in:low,medium,high,urgent',
            'order_id' => 'nullable|exists:orders,id',
            'attachments' => 'nullable|array|max:5',
            'attachments.*' => 'url|max:500',
            'metadata' => 'nullable|array',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'subject.required' => 'Subject is required for your support ticket.',
            'subject.max' => 'Subject cannot exceed 255 characters.',
            'description.required' => 'Please provide a description of your issue.',
            'description.min' => 'Description must be at least 10 characters long.',
            'description.max' => 'Description cannot exceed 2000 characters.',
            'category.required' => 'Please select a category for your ticket.',
            'category.in' => 'Please select a valid category.',
            'order_id.exists' => 'The specified order does not exist.',
            'attachments.max' => 'You can attach up to 5 files maximum.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'order_id' => 'order reference',
            'attachments' => 'file attachments',
        ];
    }

    /**
     * Get the category display names.
     */
    public static function getCategoryOptions(): array
    {
        return [
            'order_issue' => 'Order Issue',
            'payment_issue' => 'Payment Issue',
            'driver_complaint' => 'Driver Complaint',
            'app_bug' => 'App Bug/Technical Issue',
            'feature_request' => 'Feature Request',
            'account_issue' => 'Account Issue',
            'refund_request' => 'Refund Request',
            'general_inquiry' => 'General Inquiry',
            'other' => 'Other',
        ];
    }

    /**
     * Get the priority display names.
     */
    public static function getPriorityOptions(): array
    {
        return [
            'low' => 'Low',
            'medium' => 'Medium',
            'high' => 'High',
            'urgent' => 'Urgent',
        ];
    }
}