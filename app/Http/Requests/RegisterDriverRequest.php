<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\File;

class RegisterDriverRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Public endpoint
    }

    public function rules(): array
    {
        $documentRule = ['required', File::types(['jpg', 'jpeg', 'png', 'pdf', 'doc', 'docx'])->max(10240)];

        return [
            'name'                    => ['required', 'string', 'max:255'],
            'email'                   => ['required', 'email', 'max:255', 'unique:users,email'],
            'phone_number'            => ['required', 'string', 'max:50'],
            'password'                => ['required', 'string', 'min:8', 'confirmed'],
            'password_confirmation'   => ['required', 'string'],
            'partner_id'              => ['nullable', 'integer', 'exists:partners,id'],
            'license_number'          => ['required', 'string', 'max:100'],
            'license_expiry_date'     => ['nullable', 'date', 'after:today'],
            'last_latitude'           => ['nullable', 'numeric', 'between:-90,90'],
            'last_longitude'          => ['nullable', 'numeric', 'between:-180,180'],
            'selfie'                  => $documentRule,
            'selfie_with_motorcycle'  => $documentRule,
            'resume'                  => $documentRule,
            'barangay_clearance'      => $documentRule,
            'drivers_license'         => $documentRule,
            'motorcycle_orcr'         => $documentRule,
            'scanned_orcr'            => $documentRule,
            'digital_signature'       => ['required', File::types(['pdf'])->max(10240)],
        ];
    }

    public function messages(): array
    {
        return [
            'email.unique'                  => 'This email is already registered.',
            'password.min'                  => 'Password must be at least 8 characters.',
            'password.confirmed'             => 'Password confirmation does not match.',
            'license_expiry_date.after'     => 'License expiry date must be in the future.',
            'partner_id.exists'             => 'The specified partner does not exist.',
            'digital_signature.mimes'         => 'Digital signature must be uploaded as a PDF file.',
        ];
    }
}
