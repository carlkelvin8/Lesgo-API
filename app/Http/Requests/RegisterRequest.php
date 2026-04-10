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
            'phone_number' => [
                'nullable',
                'string',
                'max:20',
                'regex:/^[\d\s\+\-\(\)]+$/',
            ],
            'date_of_birth' => [
                'nullable',
                'date',
                'before:today',
                'after:1900-01-01',
            ],
            'address_line1' => ['nullable', 'string', 'max:255'],
            'address_line2' => ['nullable', 'string', 'max:255'],
            'profile_photo_url' => ['nullable', 'url', 'max:500'],
            'referred_by' => ['nullable', 'string', 'max:20', 'exists:users,referral_code'],
            'password' => [
                'required',
                'confirmed',
                Password::min(8)
                    ->mixedCase()
                    ->numbers(),
            ],
            'role' => [
                'required',
                'string',
                'in:customer,driver,partner_admin',
            ],
            'device_name' => [
                'nullable',
                'string',
                'max:100',
            ],
        ];
    }

    public function messages(): array
    {
        return [];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => trim($this->name ?? ''),
            'email' => strtolower(trim($this->email ?? '')),
            'phone_number' => $this->phone_number ? preg_replace('/[^\d\+]/', '', $this->phone_number) : null,
        ]);
    }
}
