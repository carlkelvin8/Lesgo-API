<?php

namespace Database\Seeders;

use App\Models\SecuritySetting;
use App\Services\WalletValidationService;
use Illuminate\Database\Seeder;

class WalletThresholdSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create the wallet minimum balance threshold setting
        SecuritySetting::updateOrCreate(
            ['setting_key' => WalletValidationService::THRESHOLD_SETTING_KEY],
            [
                'setting_value' => (string) WalletValidationService::DEFAULT_THRESHOLD,
                'data_type' => 'decimal',
                'description' => 'Minimum wallet balance required for drivers to receive or accept bookings',
                'category' => 'wallet',
                'is_sensitive' => false,
                'requires_restart' => false,
                'updated_by' => null,
                'metadata' => [
                    'currency' => 'PHP',
                    'min_value' => 0,
                    'max_value' => 10000,
                    'default_value' => WalletValidationService::DEFAULT_THRESHOLD
                ]
            ]
        );

        $this->command->info('Wallet threshold setting created successfully.');
    }
}