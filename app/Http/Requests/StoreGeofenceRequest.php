<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreGeofenceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'type' => 'required|in:delivery_zone,service_area,restricted_area,pickup_zone,partner_location',
            'shape' => 'required|in:circle,polygon',
            'center_latitude' => 'required_if:shape,circle|numeric|between:-90,90',
            'center_longitude' => 'required_if:shape,circle|numeric|between:-180,180',
            'radius_meters' => 'required_if:shape,circle|integer|min:1|max:50000',
            'polygon_coordinates' => 'required_if:shape,polygon|array|min:3',
            'polygon_coordinates.*' => 'array|size:2',
            'polygon_coordinates.*.0' => 'numeric|between:-180,180', // longitude
            'polygon_coordinates.*.1' => 'numeric|between:-90,90',   // latitude
            'trigger_on_enter' => 'boolean',
            'trigger_on_exit' => 'boolean',
            'trigger_on_dwell' => 'boolean',
            'dwell_time_minutes' => 'nullable|integer|min:1|max:1440',
            'notification_types' => 'array',
            'notification_types.*' => 'in:push,sms,email,webhook',
            'webhook_url' => 'nullable|url|max:500',
            'is_active' => 'boolean',
            'metadata' => 'nullable|array',
        ];
    }

    public function messages(): array
    {
        return [
            'polygon_coordinates.min' => 'A polygon must have at least 3 coordinate points.',
            'polygon_coordinates.*.size' => 'Each coordinate must contain exactly 2 values: [longitude, latitude].',
            'radius_meters.max' => 'Radius cannot exceed 50 kilometers.',
            'dwell_time_minutes.max' => 'Dwell time cannot exceed 24 hours.',
        ];
    }
}