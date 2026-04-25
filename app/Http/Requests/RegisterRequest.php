<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $isPartnerAdmin = $this->input('role') === 'partner_admin';

        return [
            'name' => [
                'required',
                'string',
                'max:255',
                'regex:/^[a-zA-Z\s\-\']+$/',
            ],
            'email' => [
                'required',
                'string',
                'email:rfc',
                'max:255',
                'unique:users,email',
            ],
            'phone' => [
                'required',
                'string',
                'max:20',
                'regex:/^[\d\s\+\-\(\)]+$/',
            ],
            'password' => [
                'required',
                'confirmed',
                Password::min(8)
                    ->mixedCase()
                    ->numbers(),
            ],
            'password_confirmation' => [
                'required',
                'string',
            ],
            'address_line_1' => ['nullable', 'string', 'max:255'],
            'address_line_2' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:100'],
            'province' => ['nullable', 'string', 'max:100'],
            'zip_code' => ['nullable', 'string', 'max:20'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'terms_accepted' => ['required', 'boolean', 'accepted'],
            'privacy_accepted' => ['required', 'boolean', 'accepted'],
            'role' => [
                'sometimes',
                'string',
                'in:customer,driver,partner_admin',
            ],
            'device_name' => [
                'nullable',
                'string',
                'max:100',
            ],

            // ── Partner Admin fields ──────────────────────────────────────────
            'restaurant_name'         => [$isPartnerAdmin ? 'required' : 'nullable', 'string', 'max:255'],
            'zip_code'                => ['nullable', 'string', 'max:20'],
            'selfie_path'             => [$isPartnerAdmin ? 'required' : 'nullable', 'string', 'max:500'],
            'valid_id_path'           => [$isPartnerAdmin ? 'required' : 'nullable', 'string', 'max:500'],
            'digital_signature_path'  => [$isPartnerAdmin ? 'required' : 'nullable', 'string', 'max:500'],
            'barangay_permit_path'    => ['nullable', 'string', 'max:500'],
            'mayors_permit_path'      => ['nullable', 'string', 'max:500'],
            'dti_permit_path'         => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name'  => trim($this->name ?? ''),
            'email' => strtolower(trim($this->email ?? '')),
            // Accept both 'phone' and 'phone_number' field names
            'phone' => $this->phone ?? $this->phone_number
                ? preg_replace('/[^\d\+]/', '', $this->phone ?? $this->phone_number)
                : null,
            'role'  => $this->role ?? 'customer',
            // Normalize snake_case document field names
            'restaurant_name'         => $this->restaurant_name ?? $this->restaurantName ?? null,
            'selfie_path'             => $this->selfie_path ?? $this->selfiePath ?? null,
            'valid_id_path'           => $this->valid_id_path ?? $this->validIdPath ?? null,
            'digital_signature_path'  => $this->digital_signature_path ?? $this->digitalSignaturePath ?? null,
            'barangay_permit_path'    => $this->barangay_permit_path ?? $this->barangayPermitPath ?? null,
            'mayors_permit_path'      => $this->mayors_permit_path ?? $this->mayorsPermitPath ?? null,
            'dti_permit_path'         => $this->dti_permit_path ?? $this->dtiPermitPath ?? null,
        ]);
    }
}
