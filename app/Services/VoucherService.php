<?php

namespace App\Services;

use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class VoucherService
{
    /**
     * Apply voucher to order and calculate discount
     */
    public function applyVoucher(Order $order, string $voucherCode): array
    {
        $voucher = $this->validateVoucher($voucherCode, $order);
        
        if (!$voucher['valid']) {
            return $voucher;
        }
        
        $discount = $this->calculateDiscount($order, $voucher['voucher']);
        
        // Update order with voucher details
        $order->update([
            'voucher_code' => $voucherCode,
            'discount_amount' => $discount['amount'],
            'meta' => array_merge($order->meta ?? [], [
                'voucher_details' => $voucher['voucher'],
                'discount_breakdown' => $discount
            ])
        ]);
        
        // Track voucher usage
        $this->trackVoucherUsage($voucherCode, $order, $discount);
        
        return [
            'valid' => true,
            'discount_amount' => $discount['amount'],
            'discount_type' => $discount['type'],
            'new_total' => $order->estimated_fare - $discount['amount'],
            'voucher_details' => $voucher['voucher']
        ];
    }
    
    /**
     * Validate voucher without applying it (for preview/validation only)
     */
    public function validateVoucherOnly(Order $order, string $voucherCode): array
    {
        $voucher = $this->validateVoucher($voucherCode, $order);
        
        if (!$voucher['valid']) {
            return $voucher;
        }
        
        $discount = $this->calculateDiscount($order, $voucher['voucher']);
        
        return [
            'valid' => true,
            'discount_amount' => $discount['amount'],
            'discount_type' => $discount['type'],
            'new_total' => $order->estimated_fare - $discount['amount'],
            'voucher_details' => $voucher['voucher']
        ];
    }
    
    /**
     * Validate voucher code and eligibility
     */
    private function validateVoucher(string $code, Order $order): array
    {
        $voucher = $this->getVoucherByCode($code);
        
        if (!$voucher) {
            return ['valid' => false, 'error' => 'Invalid voucher code'];
        }
        
        // Check expiry
        if ($voucher['expires_at'] && Carbon::parse($voucher['expires_at'])->isPast()) {
            return ['valid' => false, 'error' => 'Voucher has expired'];
        }
        
        // Check usage limits
        if ($voucher['max_uses'] && $voucher['used_count'] >= $voucher['max_uses']) {
            return ['valid' => false, 'error' => 'Voucher usage limit reached'];
        }
        
        // Check user eligibility
        if (!$this->isUserEligible($order->customer_id, $voucher)) {
            return ['valid' => false, 'error' => 'You are not eligible for this voucher'];
        }
        
        // Check minimum order value
        if ($voucher['min_order_value'] && $order->estimated_fare < $voucher['min_order_value']) {
            return [
                'valid' => false, 
                'error' => "Minimum order value of ₱{$voucher['min_order_value']} required"
            ];
        }
        
        // Check service type eligibility
        if ($voucher['applicable_services'] && !in_array($order->service_id, $voucher['applicable_services'])) {
            return ['valid' => false, 'error' => 'Voucher not applicable to this service type'];
        }
        
        return ['valid' => true, 'voucher' => $voucher];
    }
    
    /**
     * Calculate discount amount based on voucher type
     */
    private function calculateDiscount(Order $order, array $voucher): array
    {
        $baseAmount = $order->estimated_fare;
        
        switch ($voucher['type']) {
            case 'percentage':
                $discountAmount = ($baseAmount * $voucher['value']) / 100;
                if ($voucher['max_discount']) {
                    $discountAmount = min($discountAmount, $voucher['max_discount']);
                }
                break;
                
            case 'fixed':
                $discountAmount = min($voucher['value'], $baseAmount);
                break;
                
            case 'free_delivery':
                // Calculate delivery fee portion
                $deliveryFee = $this->calculateDeliveryFee($order);
                $discountAmount = min($deliveryFee, $baseAmount);
                break;
                
            case 'buy_one_get_one':
                // For multiple items, discount the cheaper one
                $discountAmount = $this->calculateBOGODiscount($order);
                break;
                
            default:
                $discountAmount = 0;
        }
        
        return [
            'type' => $voucher['type'],
            'amount' => round($discountAmount, 2),
            'original_value' => $voucher['value'],
            'applied_to' => $baseAmount
        ];
    }
    
    /**
     * Get voucher details by code (simulated database)
     */
    private function getVoucherByCode(string $code): ?array
    {
        // In a real implementation, this would query a vouchers table
        // For now, simulate with predefined vouchers
        $vouchers = [
            'WELCOME10' => [
                'code' => 'WELCOME10',
                'type' => 'percentage',
                'value' => 10,
                'max_discount' => 50,
                'min_order_value' => 100,
                'max_uses' => 1000,
                'used_count' => 150,
                'expires_at' => '2024-12-31',
                'user_restrictions' => ['new_users_only' => true],
                'applicable_services' => null, // All services
                'description' => '10% off for new users'
            ],
            'SAVE20' => [
                'code' => 'SAVE20',
                'type' => 'fixed',
                'value' => 20,
                'max_discount' => null,
                'min_order_value' => 150,
                'max_uses' => 500,
                'used_count' => 89,
                'expires_at' => '2024-06-30',
                'user_restrictions' => [],
                'applicable_services' => null,
                'description' => '₱20 off orders above ₱150'
            ],
            'FREEDEL' => [
                'code' => 'FREEDEL',
                'type' => 'free_delivery',
                'value' => 0,
                'max_discount' => null,
                'min_order_value' => 200,
                'max_uses' => null,
                'used_count' => 0,
                'expires_at' => null,
                'user_restrictions' => [],
                'applicable_services' => [1, 2], // Only certain services
                'description' => 'Free delivery on orders above ₱200'
            ]
        ];
        
        return $vouchers[strtoupper($code)] ?? null;
    }
    
    /**
     * Check if user is eligible for voucher
     */
    private function isUserEligible(int $userId, array $voucher): bool
    {
        $restrictions = $voucher['user_restrictions'] ?? [];
        
        // Check if new users only
        if (isset($restrictions['new_users_only']) && $restrictions['new_users_only']) {
            $user = User::find($userId);
            $orderCount = Order::where('customer_id', $userId)->count();
            
            if ($orderCount > 0) {
                return false; // Not a new user
            }
        }
        
        // Check usage per user limit
        if (isset($restrictions['max_uses_per_user'])) {
            $userUsageCount = $this->getUserVoucherUsageCount($userId, $voucher['code']);
            if ($userUsageCount >= $restrictions['max_uses_per_user']) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Calculate delivery fee portion of order
     */
    private function calculateDeliveryFee(Order $order): float
    {
        // Estimate delivery fee based on distance
        $distanceKm = $order->estimated_distance_m / 1000;
        return max(30, $distanceKm * 8); // Minimum ₱30, ₱8 per km
    }
    
    /**
     * Calculate BOGO discount
     */
    private function calculateBOGODiscount(Order $order): float
    {
        $items = $order->lesbuyItems;
        
        if ($items->count() < 2) {
            return 0;
        }
        
        // Find the cheapest item to discount
        $cheapestPrice = $items->min('estimated_price') ?? 0;
        return $cheapestPrice;
    }
    
    /**
     * Track voucher usage
     */
    private function trackVoucherUsage(string $code, Order $order, array $discount): void
    {
        // In a real implementation, this would update voucher usage in database
        // and create usage tracking records
        
        Cache::increment("voucher_usage:{$code}");
        
        // Log voucher usage for analytics
        Log::info('Voucher applied', [
            'voucher_code' => $code,
            'order_id' => $order->id,
            'customer_id' => $order->customer_id,
            'discount_amount' => $discount['amount'],
            'original_fare' => $order->estimated_fare
        ]);
    }
    
    /**
     * Get user's voucher usage count
     */
    private function getUserVoucherUsageCount(int $userId, string $code): int
    {
        return Order::where('customer_id', $userId)
                   ->where('voucher_code', $code)
                   ->count();
    }
    
    /**
     * Get available vouchers for user
     */
    public function getAvailableVouchers(User $user): array
    {
        $allVouchers = [
            'WELCOME10' => [
                'code' => 'WELCOME10',
                'title' => 'Welcome Discount',
                'description' => '10% off your first order',
                'discount_text' => '10% OFF',
                'min_order' => '₱100',
                'expires_at' => '2024-12-31',
                'type' => 'percentage',
                'value' => 10
            ],
            'SAVE20' => [
                'code' => 'SAVE20',
                'title' => 'Save Big',
                'description' => '₱20 off orders above ₱150',
                'discount_text' => '₱20 OFF',
                'min_order' => '₱150',
                'expires_at' => '2024-06-30',
                'type' => 'fixed',
                'value' => 20
            ],
            'FREEDEL' => [
                'code' => 'FREEDEL',
                'title' => 'Free Delivery',
                'description' => 'Free delivery on orders above ₱200',
                'discount_text' => 'FREE DELIVERY',
                'min_order' => '₱200',
                'expires_at' => null,
                'type' => 'free_delivery',
                'value' => 0
            ]
        ];
        
        $availableVouchers = [];
        
        foreach ($allVouchers as $code => $voucher) {
            $validation = $this->validateVoucherForUser($code, $user);
            if ($validation['eligible']) {
                $voucher['eligible'] = true;
                $availableVouchers[] = $voucher;
            } else {
                $voucher['eligible'] = false;
                $voucher['reason'] = $validation['reason'];
                $availableVouchers[] = $voucher;
            }
        }
        
        return $availableVouchers;
    }
    
    /**
     * Validate voucher for user without order context
     */
    private function validateVoucherForUser(string $code, User $user): array
    {
        $voucher = $this->getVoucherByCode($code);
        
        if (!$voucher) {
            return ['eligible' => false, 'reason' => 'Invalid voucher'];
        }
        
        // Check expiry
        if ($voucher['expires_at'] && Carbon::parse($voucher['expires_at'])->isPast()) {
            return ['eligible' => false, 'reason' => 'Expired'];
        }
        
        // Check user eligibility
        if (!$this->isUserEligible($user->id, $voucher)) {
            return ['eligible' => false, 'reason' => 'Not eligible'];
        }
        
        return ['eligible' => true];
    }
}