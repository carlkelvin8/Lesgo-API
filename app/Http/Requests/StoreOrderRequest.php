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
            // Core
            'service_id'           => ['required', 'integer', 'exists:services,id'],
            'partner_id'           => ['nullable', 'integer', 'exists:partners,id'],
            'scheduled_at'         => ['nullable', 'date'],
            'estimated_distance_m' => ['required', 'integer', 'min:1', 'max:30000'],
            'payment_method'       => ['nullable', 'string', 'in:cash,gcash,maya,card,wallet'],
            'notes'                => ['nullable', 'string', 'max:500'],
            'voucher_code'         => ['nullable', 'string', 'max:50'],
            'vehicle_type'         => ['nullable', 'string', 'in:motor,car,van,bicycle'],
            'passenger_name'       => ['nullable', 'string', 'max:255'],

            // LesGo-specific fields (shown on the booking screen)
            'item_description'     => ['nullable', 'string', 'max:500'],   // "Books, Clothes, Electronics"
            'estimated_weight_kg'  => ['nullable', 'numeric', 'min:0', 'max:100'],

            // Pickup — inline address (required)
            'pickup'                      => ['required', 'array'],
            'pickup.address'              => ['required', 'string', 'max:255'],
            'pickup.lat'                  => ['required', 'numeric', 'between:-90,90'],
            'pickup.lng'                  => ['required', 'numeric', 'between:-180,180'],
            'pickup.contact_name'         => ['nullable', 'string', 'max:255'],
            'pickup.contact_phone'        => ['nullable', 'string', 'max:50'],

            // Dropoff — inline address (required)
            'dropoff'                     => ['required', 'array'],
            'dropoff.address'             => ['required', 'string', 'max:255'],
            'dropoff.lat'                 => ['required', 'numeric', 'between:-90,90'],
            'dropoff.lng'                 => ['required', 'numeric', 'between:-180,180'],
            'dropoff.contact_name'        => ['nullable', 'string', 'max:255'],
            'dropoff.contact_phone'       => ['nullable', 'string', 'max:50'],

            // Optionally save addresses to user's address book
            'save_addresses'       => ['nullable', 'boolean'],
            'pickup_label'         => ['nullable', 'string', 'max:100'],
            'dropoff_label'        => ['nullable', 'string', 'max:100'],

            // Order items (for LesBuy / LesEat)
            'items'                        => ['nullable', 'array', 'max:50'],
            'items.*.name'                 => ['required_with:items', 'string', 'max:255'],
            'items.*.quantity'             => ['required_with:items', 'integer', 'min:1'],
            'items.*.unit'                 => ['nullable', 'string', 'max:50'],
            'items.*.notes'                => ['nullable', 'string', 'max:255'],
            'items.*.image_url'            => ['nullable', 'url', 'max:500'],
            'items.*.estimated_price'      => ['nullable', 'numeric', 'min:0'],
            'items.*.is_checklist_item'    => ['nullable', 'boolean'],

            // Extra meta
            'meta'                 => ['nullable', 'array'],
            'meta.order_value'     => ['nullable', 'numeric', 'min:0'],
            'meta.special_instructions' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'service_id.exists'              => 'The selected service does not exist.',
            'estimated_distance_m.required'  => 'Distance is required.',
            'estimated_distance_m.max'       => 'Distance cannot exceed 30km.',
            'pickup.required'                => 'Pickup information is required.',
            'pickup.address.required'        => 'Pickup address is required.',
            'pickup.lat.required'            => 'Pickup latitude is required.',
            'pickup.lng.required'            => 'Pickup longitude is required.',
            'dropoff.required'               => 'Dropoff information is required.',
            'dropoff.address.required'       => 'Dropoff address is required.',
            'dropoff.lat.required'           => 'Dropoff latitude is required.',
            'dropoff.lng.required'           => 'Dropoff longitude is required.',
            'items.*.name.required_with'     => 'Each item must have a name.',
            'items.*.quantity.required_with' => 'Each item must have a quantity.',
            'payment_method.in'              => 'Payment method must be one of: cash, gcash, maya, card, wallet.',
        ];
    }
    
    protected function failedValidation(\Illuminate\Contracts\Validation\Validator $validator)
    {
        \Log::error('Order validation failed', [
            'errors' => $validator->errors()->toArray(),
            'input' => $this->all(),
        ]);
        
        parent::failedValidation($validator);
    }
}
