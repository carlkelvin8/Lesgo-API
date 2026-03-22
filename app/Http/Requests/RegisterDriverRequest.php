<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RegisterDriverRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Public endpoint
    }

    public function rules(): array
    {
        return [
            'name'                 => ['required', 'string', 'max:255'],
            'email'                => ['required', 'email', 'max:255', 'unique:users,email'],
            'phone_number'         => ['required', 'string', 'max:50'],
            'password'             => ['required', 'string', 'min:8'],
            'partner_id'           => ['nullable', 'integer', 'exists:partners,id'],
            'license_number'       => ['required', 'string', 'max:100'],
            'license_expiry_date'  => ['nullable', 'date', 'after:today'],
            'last_latitude'        => ['nullable', 'numeric', 'between:-90,90'],
            'last_longitude'       => ['nullable', 'numeric', 'between:-180,180'],
        ];
    }

    public function messages(): array
    {
        return [
            'email.unique'                  => 'This email is already registered.',
            'password.min'                  => 'Password must be at least 8 characters.',
            'license_expiry_date.after'     => 'License expiry date must be in the future.',
            'partner_id.exists'             => 'The specified partner does not exist.',
        ];
    }
}
