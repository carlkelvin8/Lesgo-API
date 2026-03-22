<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isCustomer() ?? false;
    }

    public function rules(): array
    {
        return [
            'service_id'           => ['required', 'integer', 'exists:services,id'],
            'scheduled_at'         => ['nullable', 'date'],
            'estimated_distance_m' => ['required', 'integer', 'min:1', 'max:30000'],
            'payment_method'       => ['nullable', 'string', 'max:50'],
            'meta'                 => ['nullable', 'array'],

            'pickup'               => ['required', 'array'],
            'pickup.address'       => ['required', 'string', 'max:255'],
            'pickup.lat'           => ['required', 'numeric', 'between:-90,90'],
            'pickup.lng'           => ['required', 'numeric', 'between:-180,180'],

            'dropoff'              => ['required', 'array'],
            'dropoff.address'      => ['required', 'string', 'max:255'],
            'dropoff.lat'          => ['required', 'numeric', 'between:-90,90'],
            'dropoff.lng'          => ['required', 'numeric', 'between:-180,180'],

            'save_addresses'       => ['nullable', 'boolean'],
            'pickup_label'         => ['nullable', 'string', 'max:100'],
            'dropoff_label'        => ['nullable', 'string', 'max:100'],
            'contact_name'         => ['nullable', 'string', 'max:255'],
            'contact_phone'        => ['nullable', 'string', 'max:100'],

            'items'                     => ['nullable', 'array'],
            'items.*.name'              => ['required_with:items', 'string', 'max:255'],
            'items.*.quantity'          => ['required_with:items', 'integer', 'min:1'],
            'items.*.estimated_price'   => ['nullable', 'numeric', 'min:0'],
            'items.*.is_checklist_item' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'service_id.exists'              => 'The selected service does not exist.',
            'estimated_distance_m.max'       => 'Distance cannot exceed 30km.',
            'pickup.address.required'        => 'Pickup address is required.',
            'dropoff.address.required'       => 'Dropoff address is required.',
            'items.*.name.required_with'     => 'Each item must have a name.',
            'items.*.quantity.required_with' => 'Each item must have a quantity.',
        ];
    }
}
