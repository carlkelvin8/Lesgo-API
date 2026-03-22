<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Address;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AddressController extends Controller
{
    protected function authorizeUserAccess(Request $request, int $userId): void
    {
        $auth = $request->user();

        if (!$auth) {
            abort(401, 'Unauthenticated');
        }

        if ((int) $auth->id !== (int) $userId && !$auth->isAdmin()) {
            abort(403, 'Forbidden');
        }
    }

    protected function authorizeAddressAccess(Request $request, Address $address): void
    {
        $auth = $request->user();

        if (!$auth) {
            abort(401, 'Unauthenticated');
        }

        if ((int) $auth->id !== (int) $address->user_id && !$auth->isAdmin()) {
            abort(403, 'Forbidden');
        }
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

    public function store(Request $request, int $userId): JsonResponse
    {
        $this->authorizeUserAccess($request, $userId);

        $data = $request->validate([
            'label'         => ['nullable', 'string', 'max:100'],
            'contact_name'  => ['nullable', 'string', 'max:255'],
            'contact_phone' => ['nullable', 'string', 'max:100'],
            'address_line1' => ['required', 'string', 'max:255'],
            'address_line2' => ['nullable', 'string', 'max:255'],
            'city'          => ['nullable', 'string', 'max:100'],
            'region'        => ['nullable', 'string', 'max:100'],
            'country'       => ['nullable', 'string', 'max:100'],
            'postal_code'   => ['nullable', 'string', 'max:20'],
            'latitude'      => ['nullable', 'numeric'],
            'longitude'     => ['nullable', 'numeric'],
            'is_default'    => ['nullable', 'boolean'],
        ]);

        $data['user_id'] = $userId;

        if (!empty($data['is_default'])) {
            Address::where('user_id', $userId)->update(['is_default' => false]);
        }

        $address = Address::create($data);

        return $this->created($address, 'Address created successfully');
    }

    public function update(Request $request, Address $address): JsonResponse
    {
        $this->authorizeAddressAccess($request, $address);

        $data = $request->validate([
            'label'         => ['sometimes', 'nullable', 'string', 'max:100'],
            'contact_name'  => ['sometimes', 'nullable', 'string', 'max:255'],
            'contact_phone' => ['sometimes', 'nullable', 'string', 'max:100'],
            'address_line1' => ['sometimes', 'required', 'string', 'max:255'],
            'address_line2' => ['sometimes', 'nullable', 'string', 'max:255'],
            'city'          => ['sometimes', 'nullable', 'string', 'max:100'],
            'region'        => ['sometimes', 'nullable', 'string', 'max:100'],
            'country'       => ['sometimes', 'nullable', 'string', 'max:100'],
            'postal_code'   => ['sometimes', 'nullable', 'string', 'max:20'],
            'latitude'      => ['sometimes', 'nullable', 'numeric'],
            'longitude'     => ['sometimes', 'nullable', 'numeric'],
            'is_default'    => ['sometimes', 'nullable', 'boolean'],
        ]);

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

    public function myStore(Request $request): JsonResponse
    {
        return $this->store($request, (int) $request->user()->id);
    }
}
