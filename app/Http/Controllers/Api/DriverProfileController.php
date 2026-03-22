<?php

namespace App\Http\Controllers\Api;

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
    public function index(Request $request): JsonResponse
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

    public function show(DriverProfile $driverProfile): JsonResponse
    {
        $driverProfile->load('user', 'partner', 'vehicles');

        return $this->success($driverProfile);
    }

    public function updateStatus(UpdateDriverStatusRequest $request, DriverProfile $driverProfile): JsonResponse
    {
        $driverProfile->update($request->validated());

        return $this->success($driverProfile, 'Driver status updated successfully');
    }

    public function updateLocation(UpdateDriverLocationRequest $request, DriverProfile $driverProfile): JsonResponse
    {
        $driverProfile->update($request->validated());

        return $this->success($driverProfile, 'Driver location updated successfully');
    }

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
