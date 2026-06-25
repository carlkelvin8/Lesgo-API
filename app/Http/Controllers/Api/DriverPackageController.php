<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\DriverPackageUpgradeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DriverPackageController extends Controller
{
    public function __construct(
        private DriverPackageUpgradeService $packageUpgradeService,
    ) {}

    public function tiers(Request $request): JsonResponse
    {
        try {
            return $this->success(
                $this->packageUpgradeService->catalogFor($request->user()),
                'Package tiers retrieved successfully'
            );
        } catch (\Throwable $e) {
            return $this->error($e->getMessage(), 422);
        }
    }

    public function upgrade(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'tier' => 'required_without:xendit_invoice_id|string|in:advance,pro',
            'payment_method' => 'nullable|string|in:xendit,lespay',
            'xendit_invoice_id' => 'nullable|string',
        ]);

        try {
            if (!empty($validated['xendit_invoice_id'])) {
                $result = $this->packageUpgradeService->confirm(
                    $request->user(),
                    $validated['xendit_invoice_id'],
                );

                return $this->success($result, 'Package upgrade completed');
            }

            $result = $this->packageUpgradeService->start(
                $request->user(),
                $validated['tier'],
                $validated['payment_method'] ?? 'xendit',
            );

            $message = ($result['status'] ?? '') === 'completed'
                ? 'Package upgraded successfully'
                : 'Package upgrade invoice created';

            return $this->success($result, $message);
        } catch (\Throwable $e) {
            return $this->error($e->getMessage(), 422);
        }
    }
}
