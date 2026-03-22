<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Partner;
use App\Models\PartnerBranch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PartnerBranchController extends Controller
{
    public function index(int $partnerId): JsonResponse
    {
        $partner = Partner::findOrFail($partnerId);

        return $this->success($partner->branches()->orderBy('id', 'desc')->get());
    }

    public function store(Request $request, int $partnerId): JsonResponse
    {
        $partner = Partner::findOrFail($partnerId);

        $data = $request->validate([
            'name'          => ['required', 'string', 'max:255'],
            'phone_number'  => ['nullable', 'string', 'max:100'],
            'address_line1' => ['required', 'string', 'max:255'],
            'address_line2' => ['nullable', 'string', 'max:255'],
            'city'          => ['nullable', 'string', 'max:100'],
            'region'        => ['nullable', 'string', 'max:100'],
            'country'       => ['nullable', 'string', 'max:100'],
            'postal_code'   => ['nullable', 'string', 'max:20'],
            'latitude'      => ['nullable', 'numeric'],
            'longitude'     => ['nullable', 'numeric'],
            'is_primary'    => ['nullable', 'boolean'],
            'opening_hours' => ['nullable', 'array'],
        ]);

        $branch = $partner->branches()->create($data);

        return $this->created($branch, 'Branch created successfully');
    }

    public function update(Request $request, PartnerBranch $branch): JsonResponse
    {
        $data = $request->validate([
            'name'          => ['sometimes', 'required', 'string', 'max:255'],
            'phone_number'  => ['sometimes', 'nullable', 'string', 'max:100'],
            'address_line1' => ['sometimes', 'required', 'string', 'max:255'],
            'address_line2' => ['sometimes', 'nullable', 'string', 'max:255'],
            'city'          => ['sometimes', 'nullable', 'string', 'max:100'],
            'region'        => ['sometimes', 'nullable', 'string', 'max:100'],
            'country'       => ['sometimes', 'nullable', 'string', 'max:100'],
            'postal_code'   => ['sometimes', 'nullable', 'string', 'max:20'],
            'latitude'      => ['sometimes', 'nullable', 'numeric'],
            'longitude'     => ['sometimes', 'nullable', 'numeric'],
            'is_primary'    => ['sometimes', 'nullable', 'boolean'],
            'opening_hours' => ['sometimes', 'nullable', 'array'],
        ]);

        $branch->update($data);

        return $this->success($branch, 'Branch updated successfully');
    }

    public function destroy(PartnerBranch $branch): JsonResponse
    {
        $branch->delete();

        return $this->message('Branch deleted successfully');
    }
}
