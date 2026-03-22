<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateDriverStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        if (!$user) return false;

        // Admins can change any driver's status
        if ($user->isAdmin()) return true;

        // Partner admins can only change status of drivers belonging to their partner
        if ($user->isPartnerAdmin()) {
            $driverProfile = $this->route('driverProfile');
            $partner = $user->partner;
            return $partner && (int) $driverProfile->partner_id === (int) $partner->id;
        }

        return false;
    }

    public function rules(): array
    {
        $user = $this->user();

        // Partner admins cannot set 'active' — only admin can fully activate a driver
        $allowedStatuses = $user?->isAdmin()
            ? ['pending', 'active', 'offline', 'suspended']
            : ['offline', 'suspended'];

        return [
            'status' => ['required', 'string', 'in:' . implode(',', $allowedStatuses)],
        ];
    }

    public function messages(): array
    {
        return [
            'status.in' => 'Invalid status value for your role.',
        ];
    }
}
