<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Partner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PartnerController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Partner::query();

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        return $this->success($query->orderBy('id', 'desc')->paginate(20));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'user_id'       => ['nullable', 'integer'],
            'name'          => ['required', 'string', 'max:255'],
            'legal_name'    => ['nullable', 'string', 'max:255'],
            'slug'          => ['nullable', 'string', 'max:255', 'unique:partners,slug'],
            'business_type' => ['nullable', 'string', 'max:100'],
            'tax_id'        => ['nullable', 'string', 'max:100'],
            'support_email' => ['nullable', 'email', 'max:255'],
            'support_phone' => ['nullable', 'string', 'max:100'],
        ]);

        $partner = Partner::create($data);

        return $this->created($partner, 'Partner created successfully');
    }

    public function show(Partner $partner): JsonResponse
    {
        $partner->load('branches');

        return $this->success($partner);
    }

    public function update(Request $request, Partner $partner): JsonResponse
    {
        $data = $request->validate([
            'name'          => ['sometimes', 'required', 'string', 'max:255'],
            'legal_name'    => ['sometimes', 'nullable', 'string', 'max:255'],
            'business_type' => ['sometimes', 'nullable', 'string', 'max:100'],
            'status'        => ['sometimes', 'nullable', 'string', 'max:50'],
        ]);

        $partner->update($data);

        return $this->success($partner, 'Partner updated successfully');
    }
}
