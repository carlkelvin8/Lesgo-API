<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'order_id'           => ['required', 'integer', 'exists:orders,id'],
            'customer_id'        => ['required', 'integer', 'exists:users,id'],
            'partner_id'         => ['nullable', 'integer'],
            'driver_id'          => ['nullable', 'integer'],
            'amount'             => ['required', 'numeric', 'min:0'],
            'currency'           => ['nullable', 'string', 'max:3'],
            'method'             => ['required', 'string', 'max:50'],
            'status'             => ['nullable', 'string', 'max:50'],
            'provider'           => ['nullable', 'string', 'max:100'],
            'provider_reference' => ['nullable', 'string', 'max:255'],
            'paid_at'            => ['nullable', 'date'],
            'meta'               => ['nullable', 'array'],
        ];
    }

    public function messages(): array
    {
        return [
            'order_id.exists'    => 'The specified order does not exist.',
            'customer_id.exists' => 'The specified customer does not exist.',
            'amount.min'         => 'Amount cannot be negative.',
        ];
    }
}
