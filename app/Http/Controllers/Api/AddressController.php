<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Address;
use Illuminate\Http\Request;

/**
 * @OA\Schema(
 *     schema="Address",
 *     type="object",
 *     @OA\Property(property="id", type="integer"),
 *     @OA\Property(property="user_id", type="integer"),
 *     @OA\Property(property="label", type="string", example="Home"),
 *     @OA\Property(property="address_line1", type="string"),
 *     @OA\Property(property="city", type="string"),
 *     @OA\Property(property="is_default", type="boolean")
 * )
 */
class AddressController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/v1/users/{user_id}/addresses",
     *     summary="List user addresses",
     *     tags={"Addresses"},
     *     @OA\Parameter(
     *         name="user_id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(response=200, description="List of addresses")
     * )
     */
    public function index(int $userId)
    {
        $addresses = Address::where('user_id', $userId)
            ->orderByDesc('is_default')
            ->orderByDesc('id')
            ->get();

        return response()->json($addresses);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/users/{user_id}/addresses",
     *     summary="Create address for user",
     *     tags={"Addresses"},
     *     @OA\Parameter(
     *         name="user_id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         @OA\JsonContent(
     *             required={"address_line1"},
     *             @OA\Property(property="label", type="string"),
     *             @OA\Property(property="contact_name", type="string"),
     *             @OA\Property(property="contact_phone", type="string"),
     *             @OA\Property(property="address_line1", type="string"),
     *             @OA\Property(property="city", type="string"),
     *             @OA\Property(property="is_default", type="boolean")
     *         )
     *     ),
     *     @OA\Response(response=201, description="Address created")
     * )
     */
    public function store(Request $request, int $userId)
    {
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

        if (!empty($data['is_default']) && $data['is_default']) {
            Address::where('user_id', $userId)->update(['is_default' => false]);
        }

        $address = Address::create($data);

        return response()->json($address, 201);
    }

    /**
     * @OA\Patch(
     *     path="/api/v1/addresses/{id}",
     *     summary="Update address",
     *     tags={"Addresses"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(response=200, description="Address updated")
     * )
     */
    public function update(Request $request, Address $address)
    {
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

        if (array_key_exists('is_default', $data) && $data['is_default']) {
            Address::where('user_id', $address->user_id)
                ->where('id', '!=', $address->id)
                ->update(['is_default' => false]);
        }

        $address->update($data);

        return response()->json($address);
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/addresses/{id}",
     *     summary="Delete address",
     *     tags={"Addresses"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(response=200, description="Address deleted")
     * )
     */
    public function destroy(Address $address)
    {
        $address->delete();

        return response()->json(['message' => 'Address deleted.']);
    }
}
