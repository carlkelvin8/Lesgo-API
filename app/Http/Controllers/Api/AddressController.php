<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAddressRequest;
use App\Http\Requests\UpdateAddressRequest;
use App\Models\Address;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AddressController extends Controller
{
    protected function authorizeUserAccess(Request $request, int $userId): void
    {
        $auth = $request->user();
        if (!$auth) abort(401);
        if ((int) $auth->id !== $userId && !$auth->isAdmin()) abort(403);
    }

    protected function authorizeAddressAccess(Request $request, Address $address): void
    {
        $auth = $request->user();
        if (!$auth) abort(401);
        if ((int) $auth->id !== (int) $address->user_id && !$auth->isAdmin()) abort(403);
    }

    public function index(Request $request, int $userId): JsonResponse
    {
        $this->authorizeUserAccess($request, $userId);

        $addresses = Address::where('user_id', $userId)
            ->orderByDesc('is_default')
            ->orderByDesc('id')
            ->get();

        return $this->success($addresses);
    }

    public function store(StoreAddressRequest $request, int $userId): JsonResponse
    {
        $this->authorizeUserAccess($request, $userId);

        $data             = $request->validated();
        $data['user_id']  = $userId;

        if (!empty($data['is_default'])) {
            Address::where('user_id', $userId)->update(['is_default' => false]);
        }

        return $this->created(Address::create($data), 'Address created successfully');
    }

    public function update(UpdateAddressRequest $request, Address $address): JsonResponse
    {
        $this->authorizeAddressAccess($request, $address);

        $data = $request->validated();

        if (!empty($data['is_default'])) {
            Address::where('user_id', $address->user_id)
                ->where('id', '!=', $address->id)
                ->update(['is_default' => false]);
        }

        $address->update($data);

        return $this->success($address, 'Address updated successfully');
    }

    public function destroy(Request $request, Address $address): JsonResponse
    {
        $this->authorizeAddressAccess($request, $address);
        $address->delete();

        return $this->message('Address deleted successfully');
    }

    public function myIndex(Request $request): JsonResponse
    {
        return $this->index($request, (int) $request->user()->id);
    }

    public function myStore(StoreAddressRequest $request): JsonResponse
    {
        return $this->store($request, (int) $request->user()->id);
    }
}
