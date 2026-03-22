<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePartnerBranchRequest;
use App\Http\Requests\UpdatePartnerBranchRequest;
use App\Models\Partner;
use App\Models\PartnerBranch;
use Illuminate\Http\JsonResponse;

class PartnerBranchController extends Controller
{
    public function index(int $partnerId): JsonResponse
    {
        $partner = Partner::findOrFail($partnerId);

        return $this->success($partner->branches()->orderBy('id', 'desc')->get());
    }

    public function store(StorePartnerBranchRequest $request, int $partnerId): JsonResponse
    {
        $partner = Partner::findOrFail($partnerId);
        $branch  = $partner->branches()->create($request->validated());

        return $this->created($branch, 'Branch created successfully');
    }

    public function update(UpdatePartnerBranchRequest $request, PartnerBranch $branch): JsonResponse
    {
        $branch->update($request->validated());

        return $this->success($branch, 'Branch updated successfully');
    }

    public function destroy(PartnerBranch $branch): JsonResponse
    {
        $branch->delete();

        return $this->message('Branch deleted successfully');
    }
}
