<?php

namespace App\Services;

use App\Models\SecurityEvent;
use App\Models\User;
use App\Models\Order;
use App\Models\Payment;
use App\Models\UserSession;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;

/**
 * Anomaly Detection Service
 * 
 * Detects suspicious activities and anomalies using rule-based detection.
 * Monitors for: unusual login patterns, transaction anomalies, 
 * location-based anomalies, rate limit violations, and behavioral anomalies.
 */
class AnomalyDetectionService
{
    /**
     * Detection thresholds
     */
    private const MAX_LOGIN_ATTEMPTS_PER_HOUR = 10;
    private const MAX_FAILED_TRANSACTIONS_PER_HOUR = 5;
    private const MAX_ORDERS_PER_HOUR = 20;
    private const MAX_DISTANCE_KM_PER_HOUR = 200; // Unrealistic travel speed
    private const MAX_WALLET_TRANSACTIONS_PER_HOUR = 30;
    private const SUSPICIOUS_AMOUNT_THRESHOLD = 10000; // PHP

    /**
     * Check for login anomalies (brute force, credential stuffing)
     */
    public function checkLoginAnomaly(string $email, string $ipAddress, bool $success): array
    {
        $anomalies = [];
        $cacheKey = "login_attempts:{$ipAddress}";
        
        // Track login attempts per IP
        $attempts = Cache::get($cacheKey, []);
        $attempts[] = [
            'email' => $email,
            'success' => $success,
            'timestamp' => now()->toISOString(),
        ];
        
        // Keep only last hour
        $oneHourAgo = now()->subHour()->toISOString();
        $attempts = array_filter($attempts, fn($a) => $a['timestamp'] > $oneHourAgo);
        
        Cache::put($cacheKey, $attempts, now()->addHour());

        // Check for brute force
        $failedAttempts = array_filter($attempts, fn($a) => !$a['success']);
        if (count($failedAttempts) >= self::MAX_LOGIN_ATTEMPTS_PER_HOUR) {
            $anomalies[] = [
                'type' => 'brute_force',
                'severity' => 'high',
                'description' => "Too many failed login attempts from {$ipAddress}",
                'attempt_count' => count($failedAttempts),
            ];

            $this->logSecurityEvent('brute_force_detected', 'high', [
                'ip_address' => $ipAddress,
                'email' => $email,
                'attempt_count' => count($failedAttempts),
            ]);
        }

        // Check for credential stuffing (multiple emails from same IP)
        $uniqueEmails = array_unique(array_column($attempts, 'email'));
        if (count($uniqueEmails) > 5) {
            $anomalies[] = [
                'type' => 'credential_stuffing',
                'severity' => 'critical',
                'description' => "Possible credential stuffing from {$ipAddress}",
                'unique_emails' => count($uniqueEmails),
            ];

            $this->logSecurityEvent('credential_stuffing_detected', 'critical', [
                'ip_address' => $ipAddress,
                'unique_emails' => count($uniqueEmails),
                'emails' => array_slice($uniqueEmails, 0, 10),
            ]);
        }

        return [
            'anomalies' => $anomalies,
            'is_suspicious' => !empty($anomalies),
        ];
    }

    /**
     * Check for transaction anomalies
     */
    public function checkTransactionAnomaly(int $userId, float $amount, string $paymentMethod): array
    {
        $anomalies = [];

        // Check for unusually large transactions
        if ($amount >= self::SUSPICIOUS_AMOUNT_THRESHOLD) {
            $anomalies[] = [
                'type' => 'large_transaction',
                'severity' => 'medium',
                'description' => "Large transaction detected: PHP " . number_format($amount, 2),
                'amount' => $amount,
            ];

            $this->logSecurityEvent('large_transaction', 'medium', [
                'user_id' => $userId,
                'amount' => $amount,
                'payment_method' => $paymentMethod,
            ]);
        }

        // Check for rapid successive transactions
        $recentTransactions = Payment::where('customer_id', $userId)
            ->where('created_at', '>=', now()->subHour())
            ->count();

        if ($recentTransactions >= self::MAX_FAILED_TRANSACTIONS_PER_HOUR) {
            $anomalies[] = [
                'type' => 'rapid_transactions',
                'severity' => 'high',
                'description' => "Too many transactions in short period",
                'count' => $recentTransactions,
            ];

            $this->logSecurityEvent('rapid_transactions', 'high', [
                'user_id' => $userId,
                'transaction_count' => $recentTransactions,
            ]);
        }

        return [
            'anomalies' => $anomalies,
            'is_suspicious' => !empty($anomalies),
        ];
    }

    /**
     * Check for order anomalies
     */
    public function checkOrderAnomaly(int $userId, array $orderData): array
    {
        $anomalies = [];

        // Check for excessive orders
        $recentOrders = Order::where('customer_id', $userId)
            ->where('created_at', '>=', now()->subHour())
            ->count();

        if ($recentOrders >= self::MAX_ORDERS_PER_HOUR) {
            $anomalies[] = [
                'type' => 'excessive_orders',
                'severity' => 'high',
                'description' => "Too many orders placed in short period",
                'order_count' => $recentOrders,
            ];

            $this->logSecurityEvent('excessive_orders', 'high', [
                'user_id' => $userId,
                'order_count' => $recentOrders,
            ]);
        }

        // Check for suspicious order patterns (e.g., same address, different accounts)
        if (!empty($orderData['pickup_lat']) && !empty($orderData['pickup_lng'])) {
            $similarOrders = Order::where('pickup_lat', $orderData['pickup_lat'])
                ->where('pickup_lng', $orderData['pickup_lng'])
                ->where('customer_id', '!=', $userId)
                ->where('created_at', '>=', now()->subHours(24))
                ->distinct('customer_id')
                ->count('customer_id');

            if ($similarOrders >= 5) {
                $anomalies[] = [
                    'type' => 'shared_location_fraud',
                    'severity' => 'medium',
                    'description' => "Multiple accounts using same location",
                    'unique_users' => $similarOrders,
                ];
            }
        }

        return [
            'anomalies' => $anomalies,
            'is_suspicious' => !empty($anomalies),
        ];
    }

    /**
     * Check for location-based anomalies (impossible travel)
     */
    public function checkLocationAnomaly(int $userId, float $latitude, float $longitude): array
    {
        $anomalies = [];

        // Get user's last known location
        $lastLocation = UserSession::where('user_id', $userId)
            ->whereNotNull('last_latitude')
            ->orderByDesc('last_active_at')
            ->first();

        if ($lastLocation && $lastLocation->last_latitude && $lastLocation->last_longitude) {
            $distance = $this->calculateDistance(
                $lastLocation->last_latitude,
                $lastLocation->last_longitude,
                $latitude,
                $longitude
            );

            $timeDiff = $lastLocation->last_active_at->diffInMinutes(now());
            
            if ($timeDiff > 0) {
                $speedKmh = ($distance / $timeDiff) * 60;

                // Check if speed is unrealistic (> 200 km/h)
                if ($speedKmh > self::MAX_DISTANCE_KM_PER_HOUR) {
                    $anomalies[] = [
                        'type' => 'impossible_travel',
                        'severity' => 'critical',
                        'description' => "User appeared to travel {$distance}km in {$timeDiff} minutes ({$speedKmh} km/h)",
                        'distance_km' => round($distance, 2),
                        'time_minutes' => $timeDiff,
                        'speed_kmh' => round($speedKmh, 2),
                    ];

                    $this->logSecurityEvent('impossible_travel', 'critical', [
                        'user_id' => $userId,
                        'distance_km' => round($distance, 2),
                        'time_minutes' => $timeDiff,
                        'speed_kmh' => round($speedKmh, 2),
                        'previous_location' => [
                            'lat' => $lastLocation->last_latitude,
                            'lng' => $lastLocation->last_longitude,
                        ],
                        'current_location' => [
                            'lat' => $latitude,
                            'lng' => $longitude,
                        ],
                    ]);
                }
            }
        }

        return [
            'anomalies' => $anomalies,
            'is_suspicious' => !empty($anomalies),
        ];
    }

    /**
     * Check for wallet anomalies
     */
    public function checkWalletAnomaly(int $userId, float $amount, string $type): array
    {
        $anomalies = [];

        // Check for excessive wallet transactions
        $recentTransactions = \App\Models\WalletTransaction::where('wallet_id', function ($query) use ($userId) {
            $query->select('id')
                ->from('wallets')
                ->where('user_id', $userId);
        })->where('created_at', '>=', now()->subHour())->count();

        if ($recentTransactions >= self::MAX_WALLET_TRANSACTIONS_PER_HOUR) {
            $anomalies[] = [
                'type' => 'wallet_abuse',
                'severity' => 'high',
                'description' => "Excessive wallet transactions detected",
                'transaction_count' => $recentTransactions,
            ];
        }

        // Check for rapid top-up and withdrawal (money laundering pattern)
        if ($type === 'credit' && $amount >= 5000) {
            $recentWithdrawals = \App\Models\WalletTransaction::where('wallet_id', function ($query) use ($userId) {
                $query->select('id')
                    ->from('wallets')
                    ->where('user_id', $userId);
            })->where('type', 'debit')
                ->where('created_at', '>=', now()->subHours(2))
                ->sum('amount');

            if ($recentWithdrawals >= $amount * 0.9) {
                $anomalies[] = [
                    'type' => 'possible_money_laundering',
                    'severity' => 'critical',
                    'description' => "Possible money laundering: rapid top-up and withdrawal",
                    'deposit_amount' => $amount,
                    'withdrawal_amount' => $recentWithdrawals,
                ];
            }
        }

        return [
            'anomalies' => $anomalies,
            'is_suspicious' => !empty($anomalies),
        ];
    }

    /**
     * Get user's risk score (0-100)
     */
    public function getUserRiskScore(int $userId): int
    {
        $riskScore = 0;

        // Count unresolved security events
        $criticalEvents = SecurityEvent::where('user_id', $userId)
            ->where('severity', 'critical')
            ->where('is_resolved', false)
            ->where('detected_at', '>=', now()->subDays(7))
            ->count();

        $riskScore += $criticalEvents * 20;

        $highEvents = SecurityEvent::where('user_id', $userId)
            ->where('severity', 'high')
            ->where('is_resolved', false)
            ->where('detected_at', '>=', now()->subDays(7))
            ->count();

        $riskScore += $highEvents * 10;

        // Check for multiple devices
        $deviceCount = UserSession::where('user_id', $userId)
            ->where('last_active_at', '>=', now()->subDays(7))
            ->distinct('device_fingerprint')
            ->count('device_fingerprint');

        if ($deviceCount >= 5) {
            $riskScore += 15;
        }

        // Check for recent failed transactions
        $failedTransactions = Payment::where('customer_id', $userId)
            ->where('status', 'failed')
            ->where('created_at', '>=', now()->subDays(1))
            ->count();

        if ($failedTransactions >= 3) {
            $riskScore += 10;
        }

        return min($riskScore, 100);
    }

    /**
     * Get anomaly detection statistics
     */
    public function getStatistics(): array
    {
        return [
            'total_anomalies_today' => SecurityEvent::whereDate('detected_at', today())
                ->whereIn('event_type', [
                    'brute_force_detected',
                    'credential_stuffing_detected',
                    'impossible_travel',
                    'rapid_transactions',
                    'excessive_orders',
                    'wallet_abuse',
                    'possible_money_laundering',
                ])->count(),
            'critical_events' => SecurityEvent::where('severity', 'critical')
                ->where('is_resolved', false)
                ->count(),
            'high_risk_users' => User::whereHas('securityEvents', function ($query) {
                $query->where('severity', 'critical')
                    ->where('is_resolved', false)
                    ->where('detected_at', '>=', now()->subDays(7));
            })->count(),
            'blocked_ips_today' => \App\Models\IpBlacklist::whereDate('created_at', today())->count(),
        ];
    }

    /**
     * Calculate distance between two points (Haversine formula)
     */
    private function calculateDistance($lat1, $lng1, $lat2, $lng2): float
    {
        $earthRadius = 6371; // km

        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);

        $a = sin($dLat / 2) * sin($dLat / 2) +
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
             sin($dLng / 2) * sin($dLng / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }

    /**
     * Log security event
     */
    private function logSecurityEvent(string $eventType, string $severity, array $data): void
    {
        SecurityEvent::create([
            'user_id' => $data['user_id'] ?? null,
            'event_type' => $eventType,
            'severity' => $severity,
            'source' => 'anomaly_detection',
            'ip_address' => $data['ip_address'] ?? null,
            'description' => $data['description'] ?? "Anomaly detected: {$eventType}",
            'event_data' => $data,
            'detected_at' => now(),
        ]);

        Log::warning("Anomaly detected: {$eventType}", $data);
    }
}
