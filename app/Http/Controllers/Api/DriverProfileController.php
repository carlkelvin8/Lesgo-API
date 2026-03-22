<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DriverProfile;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use OpenApi\Annotations as OA;

/**
 * @OA\Schema(
 *     schema="DriverProfile",
 *     type="object",
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="user_id", type="integer", example=5),
 *     @OA\Property(property="partner_id", type="integer", nullable=true, example=2),
 *     @OA\Property(property="status", type="string", example="pending"),
 *     @OA\Property(property="rating", type="number", format="float", example=4.8),
 *     @OA\Property(property="total_trips", type="integer", example=120),
 *     @OA\Property(property="license_number", type="string", example="D123456789"),
 *     @OA\Property(property="license_expiry_date", type="string", format="date", example="2027-12-31"),
 *     @OA\Property(property="last_latitude", type="number", format="float", example=14.676041),
 *     @OA\Property(property="last_longitude", type="number", format="float", example=121.043700),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time")
 * )
 */
class DriverProfileController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/v1/drivers",
     *     summary="List drivers",
     *     tags={"Drivers"},
     *     @OA\Parameter(
     *         name="status",
     *         in="query",
     *         required=false,
     *         description="Filter by driver status (pending, active, offline, suspended)",
     *         @OA\Schema(type="string", example="active")
     *     ),
     *     @OA\Parameter(
     *         name="partner_id",
     *         in="query",
     *         required=false,
     *         description="Filter by partner ID",
     *         @OA\Schema(type="integer", example=2)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Paginated list of driver profiles",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(ref="#/components/schemas/DriverProfile")
     *             )
     *         )
     *     )
     * )
     */
    public function index(Request $request)
    {
        $query = DriverProfile::with('user', 'partner');

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        if ($partnerId = $request->query('partner_id')) {
            $query->where('partner_id', $partnerId);
        }

        return $this->success($query->orderBy('id', 'desc')->paginate(20));
    }

    /**
     * @OA\Get(
     *     path="/api/v1/drivers/{id}",
     *     summary="Get driver profile",
     *     tags={"Drivers"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Driver profile ID",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Driver profile details",
     *         @OA\JsonContent(ref="#/components/schemas/DriverProfile")
     *     ),
     *     @OA\Response(response=404, description="Not found")
     * )
     */
    public function show(DriverProfile $driverProfile)
    {
        $driverProfile->load('user', 'partner', 'vehicles');

        return $this->success($driverProfile);
    }

    /**
     * @OA\Patch(
     *     path="/api/v1/drivers/{id}/status",
     *     summary="Update driver status",
     *     tags={"Drivers"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Driver profile ID",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"status"},
     *             @OA\Property(
     *                 property="status",
     *                 type="string",
     *                 example="active",
     *                 description="pending | active | offline | suspended"
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Status updated",
     *         @OA\JsonContent(ref="#/components/schemas/DriverProfile")
     *     ),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function updateStatus(Request $request, DriverProfile $driverProfile)
    {
        $data = $request->validate([
            'status' => ['required', 'string', 'in:pending,active,offline,suspended'],
        ]);

        $driverProfile->update([
            'status' => $data['status'],
        ]);

        return $this->success($driverProfile, 'Driver status updated successfully');
    }

    /**
     * @OA\Patch(
     *     path="/api/v1/drivers/{id}/location",
     *     summary="Update driver last location",
     *     tags={"Drivers"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Driver profile ID",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"last_latitude", "last_longitude"},
     *             @OA\Property(
     *                 property="last_latitude",
     *                 type="number",
     *                 format="float",
     *                 example=14.676041
     *             ),
     *             @OA\Property(
     *                 property="last_longitude",
     *                 type="number",
     *                 format="float",
     *                 example=121.043700
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Location updated",
     *         @OA\JsonContent(ref="#/components/schemas/DriverProfile")
     *     ),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function updateLocation(Request $request, DriverProfile $driverProfile)
    {
        $data = $request->validate([
            'last_latitude'  => ['required', 'numeric'],
            'last_longitude' => ['required', 'numeric'],
        ]);

        $driverProfile->update($data);

        return $this->success($driverProfile, 'Driver location updated successfully');
    }

    /**
     * @OA\Post(
     *     path="/api/v1/drivers/register",
     *     summary="Register a new driver (mobile app)",
     *     description="Creates a user with role=driver and a related driver profile (status=pending).",
     *     tags={"Drivers"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={
     *                 "name",
     *                 "email",
     *                 "phone_number",
     *                 "password",
     *                 "license_number"
     *             },
     *             @OA\Property(property="name", type="string", example="Juan Driver"),
     *             @OA\Property(property="email", type="string", example="juandriver@example.com"),
     *             @OA\Property(property="phone_number", type="string", example="09123456789"),
     *             @OA\Property(property="password", type="string", example="secret123"),
     *             @OA\Property(property="partner_id", type="integer", nullable=true, example=2),
     *             @OA\Property(property="license_number", type="string", example="D123456789"),
     *             @OA\Property(property="license_expiry_date", type="string", format="date", example="2027-12-31"),
     *             @OA\Property(property="last_latitude", type="number", format="float", example=14.676041),
     *             @OA\Property(property="last_longitude", type="number", format="float", example=121.043700)
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Driver registered",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(
     *                 property="user",
     *                 ref="#/components/schemas/User"
     *             ),
     *             @OA\Property(
     *                 property="driver_profile",
     *                 ref="#/components/schemas/DriverProfile"
     *             )
     *         )
     *     ),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function register(Request $request)
    {
        $data = $request->validate([
            // User fields
            'name'              => ['required', 'string', 'max:255'],
            'email'             => ['required', 'email', 'max:255', 'unique:users,email'],
            'phone_number'      => ['required', 'string', 'max:50'],
            'password'          => ['required', 'string', 'min:6'],

            // Optional: tie driver to a partner (if merchant onboarded)
            'partner_id'        => ['nullable', 'integer'],

            // Driver profile fields
            'license_number'        => ['required', 'string', 'max:100'],
            'license_expiry_date'   => ['nullable', 'date'],
            'last_latitude'         => ['nullable', 'numeric'],
            'last_longitude'        => ['nullable', 'numeric'],
        ]);

        $result = DB::transaction(function () use ($data) {

            // 1) Create user with role = driver
            $user = User::create([
                'name'         => $data['name'],
                'email'        => $data['email'],
                'phone_number' => $data['phone_number'],
                'role'         => 'driver',
                'password'     => Hash::make($data['password']),
            ]);

            // 2) Create driver profile (status = pending by default)
            $driverProfile = DriverProfile::create([
                'user_id'             => $user->id,
                'partner_id'          => $data['partner_id'] ?? null,
                'status'              => 'pending',
                'rating'              => 0.0,
                'total_trips'         => 0,
                'license_number'      => $data['license_number'],
                'license_expiry_date' => $data['license_expiry_date'] ?? null,
                'last_latitude'       => $data['last_latitude'] ?? null,
                'last_longitude'      => $data['last_longitude'] ?? null,
            ]);

            $driverProfile->load('user', 'partner');

            return [
                'user'           => $user,
                'driver_profile' => $driverProfile,
            ];
        });

        return $this->created($result, 'Driver registered successfully');
    }
}
