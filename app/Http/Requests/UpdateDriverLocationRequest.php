<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateDriverLocationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'last_latitude'  => ['required', 'numeric', 'between:-90,90'],
            'last_longitude' => ['required', 'numeric', 'between:-180,180'],
        ];
    }

    public function messages(): array
    {
        return [
            'last_latitude.between'  => 'Latitude must be between -90 and 90.',
            'last_longitude.between' => 'Longitude must be between -180 and 180.',
        ];
    }
}
