<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreRatingReviewRequest extends FormRequest
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
            'order_id' => 'required|exists:orders,id',
            'overall_rating' => 'required|integer|min:1|max:5',
            'service_rating' => 'nullable|integer|min:1|max:5',
            'driver_rating' => 'nullable|integer|min:1|max:5',
            'delivery_time_rating' => 'nullable|integer|min:1|max:5',
            'communication_rating' => 'nullable|integer|min:1|max:5',
            'professionalism_rating' => 'nullable|integer|min:1|max:5',
            'review_title' => 'nullable|string|max:255',
            'review_comment' => 'nullable|string|max:1000',
            'review_tags' => 'nullable|array|max:10',
            'review_tags.*' => 'string|max:50',
            'review_images' => 'nullable|array|max:5',
            'review_images.*' => 'url|max:500',
            'is_anonymous' => 'nullable|boolean',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'order_id.required' => 'Order ID is required to submit a review.',
            'order_id.exists' => 'The specified order does not exist.',
            'overall_rating.required' => 'Overall rating is required.',
            'overall_rating.min' => 'Rating must be at least 1 star.',
            'overall_rating.max' => 'Rating cannot exceed 5 stars.',
            'review_comment.max' => 'Review comment cannot exceed 1000 characters.',
            'review_tags.max' => 'You can add up to 10 tags maximum.',
            'review_images.max' => 'You can upload up to 5 images maximum.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'overall_rating' => 'overall rating',
            'service_rating' => 'service rating',
            'driver_rating' => 'driver rating',
            'delivery_time_rating' => 'delivery time rating',
            'communication_rating' => 'communication rating',
            'professionalism_rating' => 'professionalism rating',
            'review_comment' => 'review comment',
            'review_tags' => 'review tags',
            'review_images' => 'review images',
        ];
    }
}