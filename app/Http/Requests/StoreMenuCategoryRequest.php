<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreMenuCategoryRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Authorization is handled by policy in controller
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'min:2',
                'max:100',
            ],
            'icon_url' => [
                'nullable',
                'string',
                'max:500',
            ],
            'image' => [
                'nullable',
                'file',
                'mimes:jpeg,jpg,png,webp',
                'max:5120',
            ],
            'icon_emoji' => [
                'nullable',
                'string',
                'max:10',
            ],
            'sort_order' => [
                'nullable',
                'integer',
                'min:0',
            ],
            'is_active' => [
                'sometimes',
                'boolean',
            ],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Category name is required.',
            'name.min' => 'Category name must be at least 2 characters.',
            'name.max' => 'Category name must not exceed 100 characters.',
            'icon_emoji.max' => 'Icon emoji must not exceed 10 characters.',
            'sort_order.integer' => 'Sort order must be a valid number.',
            'sort_order.min' => 'Sort order must be at least 0.',
            'is_active.boolean' => 'Active status must be true or false.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'icon_emoji' => 'icon',
            'sort_order' => 'sort order',
            'is_active' => 'active status',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Sanitize string inputs
        if ($this->has('name')) {
            $this->merge([
                'name' => $this->sanitizeString($this->name),
            ]);
        }

        if ($this->has('icon_emoji')) {
            $this->merge([
                'icon_emoji' => $this->sanitizeString($this->icon_emoji),
            ]);
        }

        // Ensure boolean is properly cast
        if ($this->has('is_active')) {
            $this->merge([
                'is_active' => filter_var($this->is_active, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE),
            ]);
        }
    }

    /**
     * Sanitize string input
     */
    private function sanitizeString(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        // Remove HTML tags
        $value = strip_tags($value);

        // Convert special characters to HTML entities
        $value = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');

        // Trim whitespace
        $value = trim($value);

        return $value;
    }
}
