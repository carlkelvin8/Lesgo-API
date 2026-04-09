<?php

namespace Database\Seeders;

use App\Models\SecuritySetting;
use App\Models\RateLimitRule;
use App\Models\DataRetentionPolicy;
use Illuminate\Database\Seeder;

class SecuritySettingsSeeder extends Seeder
{
    public function run(): void
    {
        // Security Settings
        $settings = [
            [
                'setting_key' => 'ip_whitelist_enabled',
                'setting_value' => '0',
                'data_type' => 'boolean',
                'description' => 'Enable IP whitelist filtering',
                'category' => 'authorization',
                'is_sensitive' => false,
                'requires_restart' => false,
            ],
            [
                'setting_key' => '2fa_required_for_admin',
                'setting_value' => '1',
                'data_type' => 'boolean',
                'description' => 'Require 2FA for admin users',
                'category' => 'authentication',
                'is_sensitive' => false,
                'requires_restart' => false,
            ],
            [
                'setting_key' => 'session_timeout_minutes',
                'setting_value' => '480',
                'data_type' => 'integer',
                'description' => 'Session timeout in minutes (8 hours)',
                'category' => 'authentication',
                'is_sensitive' => false,
                'requires_restart' => false,
            ],
            [
                'setting_key' => 'max_failed_login_attempts',
                'setting_value' => '5',
                'data_type' => 'integer',
                'description' => 'Maximum failed login attempts before lockout',
                'category' => 'authentication',
                'is_sensitive' => false,
                'requires_restart' => false,
            ],
            [
                'setting_key' => 'lockout_duration_minutes',
                'setting_value' => '15',
                'data_type' => 'integer',
                'description' => 'Account lockout duration in minutes',
                'category' => 'authentication',
                'is_sensitive' => false,
                'requires_restart' => false,
            ],
            [
                'setting_key' => 'password_min_length',
                'setting_value' => '8',
                'data_type' => 'integer',
                'description' => 'Minimum password length',
                'category' => 'authentication',
                'is_sensitive' => false,
                'requires_restart' => false,
            ],
            [
                'setting_key' => 'audit_log_retention_days',
                'setting_value' => '365',
                'data_type' => 'integer',
                'description' => 'Audit log retention period in days',
                'category' => 'compliance',
                'is_sensitive' => false,
                'requires_restart' => false,
            ],
            [
                'setting_key' => 'encryption_key_rotation_days',
                'setting_value' => '90',
                'data_type' => 'integer',
                'description' => 'Encryption key rotation period in days',
                'category' => 'encryption',
                'is_sensitive' => true,
                'requires_restart' => true,
            ],
        ];

        foreach ($settings as $setting) {
            SecuritySetting::updateOrCreate(
                ['setting_key' => $setting['setting_key']],
                $setting
            );
        }

        // Rate Limit Rules
        $rateLimitRules = [
            [
                'name' => 'Authentication Endpoints',
                'endpoint_pattern' => 'api/v1/auth/*',
                'method' => 'POST',
                'max_attempts' => 5,
                'window_minutes' => 15,
                'scope' => 'ip',
                'is_active' => true,
                'priority' => 100,
            ],
            [
                'name' => 'Password Reset',
                'endpoint_pattern' => 'api/v1/auth/password/*',
                'method' => 'POST',
                'max_attempts' => 3,
                'window_minutes' => 60,
                'scope' => 'ip',
                'is_active' => true,
                'priority' => 90,
            ],
            [
                'name' => 'Payment Endpoints',
                'endpoint_pattern' => 'api/v1/payments*',
                'method' => null,
                'max_attempts' => 10,
                'window_minutes' => 5,
                'scope' => 'user',
                'is_active' => true,
                'priority' => 80,
            ],
            [
                'name' => 'Admin Endpoints',
                'endpoint_pattern' => 'api/v1/admin/*',
                'method' => null,
                'max_attempts' => 20,
                'window_minutes' => 5,
                'scope' => 'user',
                'is_active' => true,
                'priority' => 70,
            ],
            [
                'name' => 'General API',
                'endpoint_pattern' => 'api/v1/*',
                'method' => null,
                'max_attempts' => 100,
                'window_minutes' => 5,
                'scope' => 'user',
                'is_active' => true,
                'priority' => 10,
            ],
            [
                'name' => 'Global Rate Limit',
                'endpoint_pattern' => '*',
                'method' => null,
                'max_attempts' => 1000,
                'window_minutes' => 5,
                'scope' => 'global',
                'is_active' => true,
                'priority' => 1,
            ],
        ];

        foreach ($rateLimitRules as $rule) {
            RateLimitRule::updateOrCreate(
                ['name' => $rule['name']],
                $rule
            );
        }

        // Data Retention Policies
        $retentionPolicies = [
            [
                'data_type' => 'audit_logs',
                'category' => 'security',
                'retention_days' => 365,
                'deletion_method' => 'hard_delete',
                'is_active' => true,
                'description' => 'Security audit logs retention',
            ],
            [
                'data_type' => 'security_events',
                'category' => 'security',
                'retention_days' => 730,
                'deletion_method' => 'hard_delete',
                'is_active' => true,
                'description' => 'Security events retention',
            ],
            [
                'data_type' => 'analytics_events',
                'category' => 'operational',
                'retention_days' => 90,
                'deletion_method' => 'anonymize',
                'is_active' => true,
                'description' => 'Analytics events retention',
            ],
            [
                'data_type' => 'payment_security_logs',
                'category' => 'financial',
                'retention_days' => 2555, // 7 years for PCI compliance
                'deletion_method' => 'hard_delete',
                'is_active' => true,
                'description' => 'Payment security logs (PCI DSS compliance)',
            ],
            [
                'data_type' => 'user_sessions',
                'category' => 'personal',
                'retention_days' => 30,
                'deletion_method' => 'hard_delete',
                'is_active' => true,
                'description' => 'User session data retention',
            ],
        ];

        foreach ($retentionPolicies as $policy) {
            DataRetentionPolicy::updateOrCreate(
                ['data_type' => $policy['data_type'], 'category' => $policy['category']],
                $policy
            );
        }

        $this->command->info('Security settings, rate limit rules, and data retention policies seeded successfully.');
    }
}