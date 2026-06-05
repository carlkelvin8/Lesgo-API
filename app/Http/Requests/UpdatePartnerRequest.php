<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePartnerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'name'                       => ['sometimes', 'string', 'max:255'],
            'logo'                       => ['sometimes', 'file', 'mimes:jpeg,jpg,png,webp', 'max:5120'],
            'logo_url'                   => ['sometimes', 'nullable', 'string', 'max:500'],
            'cover_image_url'            => ['sometimes', 'nullable', 'string', 'max:500'],
            'description'                => ['sometimes', 'nullable', 'string', 'max:1000'],
            'category'                   => ['sometimes', 'nullable', 'string', 'max:100'],
            'tags'                       => ['sometimes', 'nullable', 'array'],
            'tags.*'                     => ['string', 'max:50'],
            'cuisine_types'              => ['sometimes', 'nullable', 'array'],
            'cuisine_types.*'            => ['string', 'max:50'],
            'delivery_fee'               => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'min_order_amount'           => ['sometimes', 'nullable', 'integer', 'min:0'],
            'estimated_delivery_minutes' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:180'],
            'is_open'                    => ['sometimes', 'boolean'],
            'is_featured'                => ['sometimes', 'boolean'],
            'accepts_online_payment'     => ['sometimes', 'boolean'],
            'opening_hours'              => ['sometimes', 'nullable', 'array'],
            'legal_name'                 => ['sometimes', 'nullable', 'string', 'max:255'],
            'business_type'              => ['sometimes', 'nullable', 'string', 'max:100'],
            'status'                     => ['sometimes', 'string', 'in:active,inactive,suspended'],
            'support_email'              => ['sometimes', 'nullable', 'email', 'max:255'],
            'support_phone'              => ['sometimes', 'nullable', 'string', 'max:100'],
        ];
    }
}
