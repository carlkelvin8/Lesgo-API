<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FilterOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'status' => ['nullable', 'string', Rule::in([
                'pending',
                'searching_driver',
                'accepted',
                'picked_up',
                'completed',
                'cancelled',
            ])],
            'payment_status' => ['nullable', 'string', Rule::in(['pending', 'paid', 'failed', 'refunded'])],
            'service_id'     => ['nullable', 'integer', 'exists:services,id'],
            'per_page'       => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
