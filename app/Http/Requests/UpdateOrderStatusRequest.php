<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateOrderStatusRequest extends FormRequest
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
                'driver_arrived_at_pickup',
                'in_progress',
                'picked_up',
                'completed',
                'cancelled',
            ])],
            'payment_status' => ['nullable', 'string', Rule::in([
                'pending',
                'paid',
                'failed',
                'refunded',
            ])],
            'cancel_reason'     => ['nullable', 'string', 'max:255'],
            'actual_distance_m' => ['nullable', 'integer', 'min:1', 'max:30000'],
        ];
    }

    public function messages(): array
    {
        return [
            'status.in'         => 'Invalid status value.',
            'payment_status.in' => 'Invalid payment status value.',
        ];
    }
}
