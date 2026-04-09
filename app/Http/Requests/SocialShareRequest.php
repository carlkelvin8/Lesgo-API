<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Models\SocialShare;

class SocialShareRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        $rules = [
            'platform' => [
                'required',
                'string',
                Rule::in(array_keys(SocialShare::getSupportedPlatforms()))
            ],
        ];

        // Add specific rules based on the route
        if ($this->route()->getName() === 'social.order.share') {
            $rules['share_type'] = [
                'required',
                'string',
                Rule::in(['order_completed', 'service_review'])
            ];
            $rules['rating'] = 'nullable|integer|min:1|max:5';
        } elseif ($this->route()->getName() === 'social.milestone.share') {
            $rules['milestone'] = [
                'required',
                'string',
                Rule::in(['first_order', '10_orders', '50_orders', '100_orders', 'loyal_customer', 'top_reviewer'])
            ];
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'platform.required' => 'Please select a social media platform.',
            'platform.in' => 'The selected platform is not supported.',
            'share_type.required' => 'Please select a share type.',
            'share_type.in' => 'The selected share type is not valid.',
            'milestone.required' => 'Please select a milestone.',
            'milestone.in' => 'The selected milestone is not valid.',
            'rating.integer' => 'Rating must be a number.',
            'rating.min' => 'Rating must be at least 1 star.',
            'rating.max' => 'Rating cannot exceed 5 stars.',
        ];
    }

    public function attributes(): array
    {
        return [
            'platform' => 'social media platform',
            'share_type' => 'share type',
            'milestone' => 'milestone',
            'rating' => 'rating',
        ];
    }
}