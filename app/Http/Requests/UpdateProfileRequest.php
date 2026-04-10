<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        // /auth/me has no {user} route param — user is always updating themselves
        $routeUser = $this->route('user');
        if ($routeUser === null) {
            return true; // self-update via /auth/me
        }
        return $this->user()->id === (int) $routeUser;
    }

    public function rules(): array
    {
        $userId = $this->user()->id;

        return [
            'name' => [
                'sometimes',
                'string',
                'max:255',
                'regex:/^[a-zA-Z\s\-\']+$/',
            ],
            'email' => [
                'sometimes',
                'string',
                'email:rfc,dns',
                'max:255',
                Rule::unique('users', 'email')->ignore($userId),
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
            'address_line1'     => ['nullable', 'string', 'max:255'],
            'address_line2'     => ['nullable', 'string', 'max:255'],
            'profile_photo_url' => ['nullable', 'url', 'max:500'],
            'current_password' => [
                'required_with:password',
                'string',
            ],
            'password' => [
                'sometimes',
                'confirmed',
                'min:8',
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('name')) {
            $this->merge(['name' => trim($this->name)]);
        }
        if ($this->has('email')) {
            $this->merge(['email' => strtolower(trim($this->email))]);
        }
        if ($this->has('phone_number')) {
            $this->merge(['phone_number' => preg_replace('/[^\d\+]/', '', $this->phone_number)]);
        }
    }
}
