<?php

namespace App\Http\Controllers\Api;

use App\Events\DriverLocationUpdated;
use App\Http\Controllers\Controller;
use App\Http\Requests\RegisterDriverRequest;
use App\Http\Requests\UpdateDriverLocationRequest;
use App\Http\Requests\UpdateDriverStatusRequest;
use App\Models\DriverProfile;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DriverProfileController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/v1/drivers",
     *     summary="List driver profiles (scoped by role)",
     *     tags={"Drivers"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="status", in="query", required=false, @OA\Schema(type="string", enum={"pending","active","offline","suspended"})),
     *     @OA\Parameter(name="partner_id", in="query", required=false, description="Admin only", @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Paginated driver profiles",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/DriverProfile")),
     *             @OA\Property(property="meta", ref="#/components/schemas/PaginationMeta"),
     *             @OA\Property(property="links", ref="#/components/schemas/PaginationLinks")
     *         )
     *     ),
     *     @OA\Response(response=403, ref="#/components/schemas/ErrorResponse")
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $user  = $request->user();
        $query = DriverProfile::with('user', 'partner');

        // Admins see all; partner_admins see only their drivers; drivers see only themselves
        if ($user->isPartnerAdmin()) {
            $partner = $user->partner;
            $query->where('partner_id', $partner?->id ?? 0);
        } elseif ($user->isDriver()) {
            $query->where('user_id', $user->id);
        } elseif (!$user->isAdmin()) {
            // customers and other roles have no business listing drivers
            return $this->error('Forbidden', 403);
        }

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        if ($partnerId = $request->query('partner_id')) {
            // Non-admins cannot override the scope with a partner_id filter
            if ($user->isAdmin()) {
                $query->where('partner_id', $partnerId);
            }
        }

        return $this->success($query->orderBy('id', 'desc')->paginate(20));
    }

    /**
     * @OA\Get(
     *     path="/api/v1/drivers/{id}",
     *     summary="Get driver profile by ID",
     *     tags={"Drivers"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Driver profile",
     *         @OA\JsonContent(@OA\Property(property="data", ref="#/components/schemas/DriverProfile"))
     *     ),
     *     @OA\Response(response=403, ref="#/components/schemas/ErrorResponse")
     * )
     */
    public function show(Request $request, DriverProfile $driverProfile): JsonResponse
    {
        $user = $request->user();

        if (!$user->isAdmin()) {
            if ($user->isPartnerAdmin()) {
                $partner = $user->partner;
                if (!$partner || (int) $driverProfile->partner_id !== (int) $partner->id) {
                    return $this->error('Forbidden', 403);
                }
            } elseif ($user->isDriver()) {
                if ((int) $driverProfile->user_id !== (int) $user->id) {
                    return $this->error('Forbidden', 403);
                }
            } else {
                return $this->error('Forbidden', 403);
            }
        }

        $driverProfile->load('user', 'partner', 'vehicles');

        return $this->success($driverProfile);
    }

    /**
     * @OA\Patch(
     *     path="/api/v1/drivers/{id}/status",
     *     summary="Update driver status (admin/partner_admin only)",
     *     tags={"Drivers"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(required=true,
     *         @OA\JsonContent(required={"status"},
     *             @OA\Property(property="status", type="string", enum={"pending","active","offline","suspended"}, example="suspended")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Status updated",
     *         @OA\JsonContent(@OA\Property(property="data", ref="#/components/schemas/DriverProfile"))
     *     ),
     *     @OA\Response(response=403, ref="#/components/schemas/ErrorResponse")
     * )
     */
    public function updateStatus(UpdateDriverStatusRequest $request, DriverProfile $driverProfile): JsonResponse
    {
        $driverProfile->update($request->validated());

        return $this->success($driverProfile, 'Driver status updated successfully');
    }

    /**
     * @OA\Patch(
     *     path="/api/v1/drivers/{id}/location",
     *     summary="Update driver GPS location (driver updates own location)",
     *     tags={"Drivers"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(required=true,
     *         @OA\JsonContent(required={"last_latitude","last_longitude"},
     *             @OA\Property(property="last_latitude", type="number", format="float", example=14.5995),
     *             @OA\Property(property="last_longitude", type="number", format="float", example=120.9842)
     *         )
     *     ),
     *     @OA\Response(response=200, description="Location updated",
     *         @OA\JsonContent(@OA\Property(property="data", ref="#/components/schemas/DriverProfile"))
     *     ),
     *     @OA\Response(response=403, ref="#/components/schemas/ErrorResponse")
     * )
     */
    public function updateLocation(UpdateDriverLocationRequest $request, DriverProfile $driverProfile): JsonResponse
    {
        $driverProfile->update($request->validated());

        // Find the driver's active order to push location to the customer too
        $activeOrder = $driverProfile->orders()
            ->whereIn('status', ['accepted', 'picked_up'])
            ->latest()
            ->first();

        broadcast(new DriverLocationUpdated($driverProfile, $activeOrder?->id))->toOthers();

        return $this->success($driverProfile, 'Driver location updated successfully');
    }

    /**
     * @OA\Post(
     *     path="/api/v1/drivers/register",
     *     summary="Register a new driver (public)",
     *     tags={"Drivers"},
     *     @OA\RequestBody(required=true,
     *         @OA\JsonContent(required={"name","email","password","phone_number","license_number"},
     *             @OA\Property(property="name", type="string", example="Pedro Santos"),
     *             @OA\Property(property="email", type="string", format="email", example="pedro@example.com"),
     *             @OA\Property(property="password", type="string", format="password"),
     *             @OA\Property(property="password_confirmation", type="string"),
     *             @OA\Property(property="phone_number", type="string", example="+639181234567"),
     *             @OA\Property(property="license_number", type="string", example="N01-23-456789"),
     *             @OA\Property(property="license_expiry_date", type="string", format="date", nullable=true),
     *             @OA\Property(property="partner_id", type="integer", nullable=true)
     *         )
     *     ),
     *     @OA\Response(response=201, description="Driver registered",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="user", ref="#/components/schemas/User"),
     *                 @OA\Property(property="driver_profile", ref="#/components/schemas/DriverProfile")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=422, ref="#/components/schemas/ErrorResponse")
     * )
     */
    public function register(RegisterDriverRequest $request): JsonResponse
    {
        $data = $request->validated();

        $result = DB::transaction(function () use ($data) {
            $user = User::create([
                'name'         => $data['name'],
                'email'        => $data['email'],
                'phone_number' => $data['phone_number'],
                'role'         => 'driver',
                'password'     => Hash::make($data['password']),
            ]);

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

            return ['user' => $user, 'driver_profile' => $driverProfile];
        });

        return $this->created($result, 'Driver registered successfully');
    }
}
