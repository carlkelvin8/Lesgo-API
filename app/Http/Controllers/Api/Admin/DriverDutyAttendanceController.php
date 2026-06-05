<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\DriverDutyAttendance;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DriverDutyAttendanceController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
        $this->middleware(function ($request, $next) {
            if (!$request->user() || !$request->user()->isAdmin()) {
                return response()->json(['success' => false, 'message' => 'Forbidden'], 403);
            }

            return $next($request);
        });
    }

    /**
     * GET /api/v1/admin/driver-duty-attendances
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'driver_profile_id' => 'nullable|integer',
            'user_id'           => 'nullable|integer',
            'date_from'         => 'nullable|date',
            'date_to'           => 'nullable|date',
            'per_page'          => 'nullable|integer|min:1|max:100',
        ]);

        $query = DriverDutyAttendance::with([
            'user:id,name,email,phone_number',
            'driverProfile:id,user_id,vehicle_type,plate_number,partner_id',
            'driverProfile.user:id,name',
        ])->where('on_duty', true);

        if (!empty($validated['driver_profile_id'])) {
            $query->where('driver_profile_id', $validated['driver_profile_id']);
        }

        if (!empty($validated['user_id'])) {
            $query->where('user_id', $validated['user_id']);
        }

        if (!empty($validated['date_from'])) {
            $query->whereDate('captured_at', '>=', $validated['date_from']);
        }

        if (!empty($validated['date_to'])) {
            $query->whereDate('captured_at', '<=', $validated['date_to']);
        }

        $perPage = (int) ($validated['per_page'] ?? 20);
        $records = $query->orderByDesc('captured_at')->paginate($perPage);

        return $this->success($records, 'Driver duty attendance records retrieved');
    }
}
