<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePartnerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'user_id'       => ['nullable', 'integer', 'exists:users,id'],
            'name'          => ['required', 'string', 'max:255'],
            'legal_name'    => ['nullable', 'string', 'max:255'],
            'slug'          => ['nullable', 'string', 'max:255', 'unique:partners,slug'],
            'business_type' => ['nullable', 'string', 'max:100'],
            'tax_id'        => ['nullable', 'string', 'max:100'],
            'support_email' => ['nullable', 'email', 'max:255'],
            'support_phone' => ['nullable', 'string', 'max:100'],
        ];
    }
}
