<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePartnerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    public function rules(): array
    {
        return [
            'user_id'                    => ['nullable', 'integer', 'exists:users,id'],
            'name'                       => ['required', 'string', 'max:255'],
            'logo_url'                   => ['nullable', 'url', 'max:500'],
            'cover_image_url'            => ['nullable', 'url', 'max:500'],
            'description'                => ['nullable', 'string', 'max:1000'],
            'category'                   => ['nullable', 'string', 'max:100'],
            'tags'                       => ['nullable', 'array'],
            'tags.*'                     => ['string', 'max:50'],
            'cuisine_types'              => ['nullable', 'array'],
            'cuisine_types.*'            => ['string', 'max:50'],
            'delivery_fee'               => ['nullable', 'numeric', 'min:0'],
            'min_order_amount'           => ['nullable', 'integer', 'min:0'],
            'estimated_delivery_minutes' => ['nullable', 'integer', 'min:1', 'max:180'],
            'is_open'                    => ['nullable', 'boolean'],
            'is_featured'                => ['nullable', 'boolean'],
            'accepts_online_payment'     => ['nullable', 'boolean'],
            'opening_hours'              => ['nullable', 'array'],
            'legal_name'                 => ['nullable', 'string', 'max:255'],
            'slug'                       => ['nullable', 'string', 'max:255', 'unique:partners,slug'],
            'business_type'              => ['nullable', 'string', 'max:100'],
            'tax_id'                     => ['nullable', 'string', 'max:100'],
            'support_email'              => ['nullable', 'email', 'max:255'],
            'support_phone'              => ['nullable', 'string', 'max:100'],
        ];
    }
}
