<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePartnerBranchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'name'                       => ['required', 'string', 'max:255'],
            'logo_url'                   => ['nullable', 'url', 'max:500'],
            'phone_number'               => ['nullable', 'string', 'max:100'],
            'address_line1'              => ['required', 'string', 'max:255'],
            'address_line2'              => ['nullable', 'string', 'max:255'],
            'city'                       => ['nullable', 'string', 'max:100'],
            'region'                     => ['nullable', 'string', 'max:100'],
            'country'                    => ['nullable', 'string', 'max:100'],
            'postal_code'                => ['nullable', 'string', 'max:20'],
            'latitude'                   => ['nullable', 'numeric', 'between:-90,90'],
            'longitude'                  => ['nullable', 'numeric', 'between:-180,180'],
            'is_primary'                 => ['nullable', 'boolean'],
            'is_open'                    => ['nullable', 'boolean'],
            'estimated_delivery_minutes' => ['nullable', 'integer', 'min:1', 'max:180'],
            'delivery_fee'               => ['nullable', 'numeric', 'min:0'],
            'opening_hours'              => ['nullable', 'array'],
        ];
    }
}
