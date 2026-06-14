<?php

namespace App\Services;

use App\Models\Order;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;

class DriverEarningsService
{
    /**
     * @return array{period: string, summary: array, entries: array<int, array>}
     *
     * @throws ValidationException
     */
    public function getEarnings(User $user, string $period = 'today'): array
    {
        if (!$user->isDriver()) {
            throw ValidationException::withMessages([
                'account' => ['Only drivers can access earnings.'],
            ]);
        }

        $profile = $user->driverProfile;
        if (!$profile) {
            throw ValidationException::withMessages([
                'account' => ['Driver profile not found.'],
            ]);
        }

        $commissionRate = RiderCommissionService::resolveCommissionRate($profile);
        $range = $this->resolvePeriodRange($period);

        $query = Order::query()
            ->where('driver_id', $profile->id)
            ->where('status', 'completed');

        if ($range['start'] !== null) {
            $query->where('completed_at', '>=', $range['start']);
        }
        if ($range['end'] !== null) {
            $query->where('completed_at', '<=', $range['end']);
        }

        $orders = $query->orderByDesc('completed_at')->get();

        $entries = [];
        $total = 0.0;

        foreach ($orders as $order) {
            $earnings = $this->resolveOrderEarnings($order, $commissionRate);
            $total += $earnings;

            $entries[] = [
                'order_id' => $order->id,
                'service_id' => $order->service_id,
                'service_name' => $this->serviceName((int) $order->service_id),
                'earnings' => round($earnings, 2),
                'completed_at' => optional($order->completed_at)?->toIso8601String(),
                'payment_method' => $order->payment_method,
            ];
        }

        $tripCount = count($entries);

        return [
            'period' => $period,
            'summary' => [
                'total_earnings' => round($total, 2),
                'completed_trips' => $tripCount,
                'average_per_trip' => $tripCount > 0 ? round($total / $tripCount, 2) : 0.0,
                'commission_rate' => $commissionRate,
                'package_tier' => $profile->package_tier ?? 'basic',
            ],
            'entries' => $entries,
        ];
    }

    private function resolvePeriodRange(string $period): array
    {
        $now = Carbon::now();

        return match ($period) {
            'today' => [
                'start' => $now->copy()->startOfDay(),
                'end' => $now->copy()->endOfDay(),
            ],
            'week' => [
                'start' => $now->copy()->startOfWeek(),
                'end' => $now->copy()->endOfWeek(),
            ],
            'month' => [
                'start' => $now->copy()->startOfMonth(),
                'end' => $now->copy()->endOfMonth(),
            ],
            'all' => ['start' => null, 'end' => null],
            default => [
                'start' => $now->copy()->startOfDay(),
                'end' => $now->copy()->endOfDay(),
            ],
        };
    }

    private function resolveOrderEarnings(Order $order, float $commissionRate): float
    {
        if ($order->driver_share !== null && (float) $order->driver_share > 0) {
            return (float) $order->driver_share;
        }

        $breakdown = is_array($order->fare_breakdown) ? $order->fare_breakdown : [];
        $shipping = (float) ($breakdown['shipping_fee'] ?? $breakdown['delivery_fee'] ?? 0);

        if ($shipping <= 0) {
            $shipping = (float) ($order->estimated_fare ?? $order->actual_fare ?? 0);
        }

        return max(0, $shipping * $commissionRate);
    }

    private function serviceName(int $serviceId): string
    {
        return match ($serviceId) {
            3 => 'LesEat',
            4 => 'LesBuy',
            2 => 'LesRide',
            default => 'Order',
        };
    }
}
