<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePartnerRequest;
use App\Http\Requests\UpdatePartnerRequest;
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

    public function store(StorePartnerRequest $request): JsonResponse
    {
        $partner = Partner::create($request->validated());

        return $this->created($partner, 'Partner created successfully');
    }

    public function show(Partner $partner): JsonResponse
    {
        $partner->load('branches');

        return $this->success($partner);
    }

    public function update(UpdatePartnerRequest $request, Partner $partner): JsonResponse
    {
        $partner->update($request->validated());

        return $this->success($partner, 'Partner updated successfully');
    }
}
