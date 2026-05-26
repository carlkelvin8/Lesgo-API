<?php

namespace App\Services;

class LesPayTopUpFeeService
{
    public const DEFAULT_FEE_RATE = 0.03;

    public static function rate(): float
    {
        return (float) config('lespay.top_up_fee_rate', self::DEFAULT_FEE_RATE);
    }

    /**
     * @return array{wallet_amount: float, fee: float, total_charged: float, fee_rate: float}
     */
    public static function calculate(float $walletAmount): array
    {
        $rate = self::rate();
        $fee = round($walletAmount * $rate, 2);
        $total = round($walletAmount + $fee, 2);

        return [
            'wallet_amount'  => round($walletAmount, 2),
            'fee'            => $fee,
            'total_charged'  => $total,
            'fee_rate'       => $rate,
        ];
    }
}
