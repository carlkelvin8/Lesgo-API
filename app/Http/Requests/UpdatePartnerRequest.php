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
            'name'          => ['sometimes', 'required', 'string', 'max:255'],
            'legal_name'    => ['sometimes', 'nullable', 'string', 'max:255'],
            'business_type' => ['sometimes', 'nullable', 'string', 'max:100'],
            'status'        => ['sometimes', 'nullable', 'string', 'in:pending,approved,suspended'],
        ];
    }
}
