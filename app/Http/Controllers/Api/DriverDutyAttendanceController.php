<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DriverDutyAttendance;
use App\Models\DriverProfile;
use App\Services\MediaStorageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DriverDutyAttendanceController extends Controller
{
    /**
     * POST /api/v1/drivers/{driverProfile}/duty-attendance
     * Driver submits attendance photo when going on duty.
     */
    public function store(Request $request, DriverProfile $driverProfile): JsonResponse
    {
        $user = $request->user();

        if (!$user->isDriver() || (int) $driverProfile->user_id !== (int) $user->id) {
            return $this->error('Forbidden', 403);
        }

        $validated = $request->validate([
            'photo'      => 'required|image|mimes:jpeg,jpg,png|max:5120',
            'latitude'   => 'nullable|numeric|between:-90,90',
            'longitude'  => 'nullable|numeric|between:-180,180',
            'captured_at'=> 'nullable|date',
        ]);

        try {
            $path = MediaStorageService::storeUploadedFile(
                $request->file('photo'),
                "duty_attendance/{$driverProfile->id}"
            );

            $attendance = DriverDutyAttendance::create([
                'driver_profile_id' => $driverProfile->id,
                'user_id'           => $user->id,
                'photo_path'        => $path,
                'on_duty'           => true,
                'latitude'          => $validated['latitude'] ?? $driverProfile->last_latitude,
                'longitude'         => $validated['longitude'] ?? $driverProfile->last_longitude,
                'captured_at'       => $validated['captured_at'] ?? now(),
            ]);

            $driverProfile->update(['is_available' => true]);

            return $this->created($attendance->load('user:id,name,email,phone_number'), 'Duty attendance recorded');
        } catch (\Throwable $e) {
            return $this->error('Failed to record duty attendance: ' . $e->getMessage(), 500);
        }
    }
}
