<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->route('user');
        $auth = $this->user();
        if (!$auth || !$user) {
            return false;
        }
        if ($auth->isAdmin()) {
            return true;
        }
        return (int) $auth->id === (int) $user->id && !$this->has('role');
    }

    public function rules(): array
    {
        return [
            'name'         => ['sometimes', 'required', 'string', 'max:255'],
            'phone_number' => ['sometimes', 'nullable', 'string', 'max:50'],
            'role'         => ['sometimes', 'nullable', 'string', 'in:admin,customer,driver,partner_admin'],
        ];
    }
}
