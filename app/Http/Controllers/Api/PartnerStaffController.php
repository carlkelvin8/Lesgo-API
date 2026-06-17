<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Partner;
use App\Models\PartnerStaff;
use App\Models\User;
use App\Services\MediaStorageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class PartnerStaffController extends Controller
{
    public function index(Partner $partner): JsonResponse
    {
        $this->authorize('view', $partner);

        $staff = $partner->staff()->with('user')->get()->map(function (PartnerStaff $member) {
            return $this->formatStaff($member);
        });

        return $this->success($staff, 'Staff list retrieved');
    }

    public function store(Request $request, Partner $partner): JsonResponse
    {
        $this->authorize('update', $partner);

        $validated = $request->validate([
            'name'     => 'required|string|max:255',
            'email'    => 'required|email|max:255|unique:users,email',
            'password' => 'required|string|min:8',
            'role'     => ['required', Rule::in(['admin', 'cook', 'cashier'])],
        ]);

        $user = User::create([
            'name'     => $validated['name'],
            'email'    => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role'     => 'partner_staff',
            'is_active' => true,
        ]);

        $staff = PartnerStaff::create([
            'partner_id' => $partner->id,
            'user_id'    => $user->id,
            'role'       => $validated['role'],
            'invited_by' => $request->user()->id,
        ]);

        $staff->load('user');

        return $this->success(
            $this->formatStaff($staff),
            'Staff member added',
            201,
        );
    }

    public function show(Partner $partner, PartnerStaff $staff): JsonResponse
    {
        $this->authorize('view', $partner);

        if ($staff->partner_id !== $partner->id) {
            return $this->error('Staff member does not belong to this partner.', 404);
        }

        $staff->load('user');

        return $this->success($this->formatStaff($staff), 'Staff details retrieved');
    }

    public function update(Request $request, Partner $partner, PartnerStaff $staff): JsonResponse
    {
        $this->authorize('update', $partner);

        if ($staff->partner_id !== $partner->id) {
            return $this->error('Staff member does not belong to this partner.', 404);
        }

        $validated = $request->validate([
            'role'        => ['sometimes', Rule::in(['admin', 'cook', 'cashier'])],
            'permissions' => 'sometimes|nullable|array',
            'is_active'   => 'sometimes|boolean',
        ]);

        $staff->update($validated);

        $staff->load('user');

        return $this->success($this->formatStaff($staff), 'Staff member updated');
    }

    public function destroy(Partner $partner, PartnerStaff $staff): JsonResponse
    {
        $this->authorize('update', $partner);

        if ($staff->partner_id !== $partner->id) {
            return $this->error('Staff member does not belong to this partner.', 404);
        }

        $user = $staff->user;

        $staff->delete();

        $user->delete();

        return $this->success(null, 'Staff member removed');
    }

    private function formatStaff(PartnerStaff $staff): array
    {
        $user = $staff->user;

        return [
            'id'            => $staff->id,
            'partner_id'    => $staff->partner_id,
            'user_id'       => $staff->user_id,
            'role'          => $staff->role,
            'permissions'   => $staff->permissions,
            'is_active'     => $staff->is_active,
            'name'          => $user->name,
            'email'         => $user->email,
            'profile_photo_url' => $user->profile_photo_url,
            'created_at'    => $staff->created_at?->toIso8601String(),
            'updated_at'    => $staff->updated_at?->toIso8601String(),
        ];
    }
}
