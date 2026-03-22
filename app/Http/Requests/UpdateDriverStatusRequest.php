<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateDriverStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', 'string', 'in:pending,active,offline,suspended'],
        ];
    }

    public function messages(): array
    {
        return [
            'status.in' => 'Status must be one of: pending, active, offline, suspended.',
        ];
    }
}
