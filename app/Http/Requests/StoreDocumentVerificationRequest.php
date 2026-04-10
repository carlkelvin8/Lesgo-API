<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Models\DocumentVerification;

class StoreDocumentVerificationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'document_type' => [
                'required',
                'string',
                Rule::in(array_keys(DocumentVerification::getDocumentTypes()))
            ],
            'document_number' => 'nullable|string|max:100',
            'document_urls' => 'required|array|min:1|max:5',
            'document_urls.*' => 'url|max:500',
            'description' => 'nullable|string|max:500',
            'expires_at' => 'nullable|date|after:today',
            'metadata' => 'nullable|array',
        ];
    }

    public function messages(): array
    {
        return [
            'document_type.required' => 'Document type is required.',
            'document_type.in' => 'Invalid document type selected.',
            'document_urls.required' => 'At least one document image is required.',
            'document_urls.min' => 'At least one document image is required.',
            'document_urls.max' => 'Maximum 5 document images allowed.',
            'document_urls.*.url' => 'Each document URL must be a valid URL.',
            'expires_at.after' => 'Expiration date must be in the future.',
        ];
    }

    public function attributes(): array
    {
        return [
            'document_type' => 'document type',
            'document_number' => 'document number',
            'document_urls' => 'document images',
            'expires_at' => 'expiration date',
        ];
    }
}