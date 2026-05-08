<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMenuItemRequest extends FormRequest
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
        $menuItem = $this->route('menuItem');
        $partnerId = $menuItem?->partner_id;

        return [
            'menu_category_id' => [
                'sometimes',
                'integer',
                Rule::exists('menu_categories', 'id')->where(function ($query) use ($partnerId) {
                    if ($partnerId) {
                        $query->where('partner_id', $partnerId);
                    }
                }),
            ],
            'name' => [
                'sometimes',
                'string',
                'min:2',
                'max:255',
            ],
            'description' => [
                'nullable',
                'string',
                'max:1000',
            ],
            'price' => [
                'sometimes',
                'numeric',
                'min:0',
                'max:999999.99',
            ],
            'image_url' => [
                'nullable',
                'url',
                'max:500',
            ],
            'is_available' => [
                'sometimes',
                'boolean',
            ],
            'is_popular' => [
                'sometimes',
                'boolean',
            ],
            'sort_order' => [
                'nullable',
                'integer',
                'min:0',
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
            'menu_category_id.exists' => 'The selected category does not exist or does not belong to this partner.',
            'name.min' => 'Menu item name must be at least 2 characters.',
            'name.max' => 'Menu item name must not exceed 255 characters.',
            'description.max' => 'Description must not exceed 1000 characters.',
            'price.numeric' => 'Price must be a valid number.',
            'price.min' => 'Price must be at least 0.',
            'price.max' => 'Price must not exceed 999,999.99.',
            'image_url.url' => 'Image URL must be a valid URL.',
            'image_url.max' => 'Image URL must not exceed 500 characters.',
            'is_available.boolean' => 'Availability status must be true or false.',
            'is_popular.boolean' => 'Popular status must be true or false.',
            'sort_order.integer' => 'Sort order must be a valid number.',
            'sort_order.min' => 'Sort order must be at least 0.',
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
            'menu_category_id' => 'category',
            'is_available' => 'availability status',
            'is_popular' => 'popular status',
            'sort_order' => 'sort order',
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

        if ($this->has('description')) {
            $this->merge([
                'description' => $this->sanitizeString($this->description),
            ]);
        }

        // Ensure booleans are properly cast
        if ($this->has('is_available')) {
            $this->merge([
                'is_available' => filter_var($this->is_available, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE),
            ]);
        }

        if ($this->has('is_popular')) {
            $this->merge([
                'is_popular' => filter_var($this->is_popular, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE),
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
