<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        if (!$user) return false;

        // Admin can create payments for anyone
        if ($user->isAdmin()) return true;

        // Customer can only create a payment for themselves
        return (int) $this->input('customer_id') === (int) $user->id;
    }

    public function rules(): array
    {
        $user = $this->user();

        return [
            'order_id'    => ['required', 'integer', 'exists:orders,id'],
            'customer_id' => ['required', 'integer', 'exists:users,id'],
            'partner_id'  => ['nullable', 'integer', 'exists:partners,id'],
            'driver_id'   => ['nullable', 'integer', 'exists:driver_profiles,id'],
            'amount'      => ['required', 'numeric', 'min:0.01'],
            'currency'    => ['nullable', 'string', 'size:3'],
            'method'      => ['required', 'string', 'in:cash,gcash,maya,card,wallet'],
            'meta'        => ['nullable', 'array'],

            // Only admin can set these — ignored for regular users (stripped in controller)
            'status'             => $user?->isAdmin() ? ['nullable', 'string', 'in:pending,paid,failed,refunded'] : ['prohibited'],
            'provider'           => $user?->isAdmin() ? ['nullable', 'string', 'max:100'] : ['prohibited'],
            'provider_reference' => $user?->isAdmin() ? ['nullable', 'string', 'max:255'] : ['prohibited'],
            'paid_at'            => $user?->isAdmin() ? ['nullable', 'date'] : ['prohibited'],
        ];
    }

    public function messages(): array
    {
        return [
            'order_id.exists'            => 'The specified order does not exist.',
            'customer_id.exists'         => 'The specified customer does not exist.',
            'amount.min'                 => 'Amount must be greater than zero.',
            'method.in'                  => 'Payment method must be one of: cash, gcash, maya, card, wallet.',
            'status.prohibited'          => 'You are not allowed to set payment status.',
            'provider.prohibited'        => 'You are not allowed to set payment provider.',
            'provider_reference.prohibited' => 'You are not allowed to set provider reference.',
            'paid_at.prohibited'         => 'You are not allowed to set paid_at.',
        ];
    }
}
