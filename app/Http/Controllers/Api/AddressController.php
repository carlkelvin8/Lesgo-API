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

    /**
     * @OA\Get(
     *     path="/api/v1/users/{userId}/addresses",
     *     summary="List addresses for a user",
     *     tags={"Addresses"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="userId", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="List of addresses",
     *         @OA\JsonContent(@OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/Address")))
     *     ),
     *     @OA\Response(response=403, ref="#/components/schemas/ErrorResponse")
     * )
     */
    public function index(Request $request, int $userId): JsonResponse
    {
        $this->authorizeUserAccess($request, $userId);

        $addresses = Address::where('user_id', $userId)
            ->orderByDesc('is_default')
            ->orderByDesc('id')
            ->get();

        return $this->success($addresses);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/users/{userId}/addresses",
     *     summary="Create an address for a user",
     *     tags={"Addresses"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="userId", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(required=true, @OA\JsonContent(required={"address_line1","latitude","longitude"},
     *         @OA\Property(property="label", type="string", example="Home"),
     *         @OA\Property(property="address_line1", type="string", example="123 Rizal St"),
     *         @OA\Property(property="latitude", type="number", format="float", example=14.5995),
     *         @OA\Property(property="longitude", type="number", format="float", example=120.9842),
     *         @OA\Property(property="is_default", type="boolean", example=false)
     *     )),
     *     @OA\Response(response=201, description="Address created",
     *         @OA\JsonContent(@OA\Property(property="data", ref="#/components/schemas/Address"))
     *     )
     * )
     */
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

    /**
     * @OA\Patch(
     *     path="/api/v1/addresses/{id}",
     *     summary="Update an address",
     *     tags={"Addresses"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(required=true, @OA\JsonContent(
     *         @OA\Property(property="label", type="string"),
     *         @OA\Property(property="address_line1", type="string"),
     *         @OA\Property(property="is_default", type="boolean")
     *     )),
     *     @OA\Response(response=200, description="Address updated",
     *         @OA\JsonContent(@OA\Property(property="data", ref="#/components/schemas/Address"))
     *     ),
     *     @OA\Response(response=403, ref="#/components/schemas/ErrorResponse")
     * )
     */
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

    /**
     * @OA\Delete(
     *     path="/api/v1/addresses/{id}",
     *     summary="Delete an address",
     *     tags={"Addresses"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Address deleted"),
     *     @OA\Response(response=403, ref="#/components/schemas/ErrorResponse")
     * )
     */
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
