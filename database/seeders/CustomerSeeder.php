<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class CustomerSeeder extends Seeder
{
    /**
     * Seed test customer accounts for local / staging use.
     *
     * Run: php artisan db:seed --class=CustomerSeeder
     */
    public function run(): void
    {
        $accounts = [
            [
                'email'        => 'customer@lesgo.test',
                'name'         => 'Test Customer',
                'phone_number' => '+639912345678',
                'password'     => 'password',
            ],
            [
                'email'        => 'test@example.com',
                'name'         => 'Test User',
                'phone_number' => '+639154189962',
                'password'     => 'password',
            ],
        ];

        foreach ($accounts as $account) {
            $customer = User::firstOrCreate(
                ['email' => $account['email']],
                [
                    'name'         => $account['name'],
                    'password'     => Hash::make($account['password']),
                    'role'         => 'customer',
                    'phone_number' => $account['phone_number'],
                    'referral_code'=> strtoupper(Str::random(8)),
                    'points'       => 0,
                ]
            );

            if ($customer->email_verified_at === null) {
                $customer->email_verified_at = now();
            }

            if ($customer->phone_verified_at === null) {
                $customer->phone_verified_at = now();
            }

            if ($customer->referral_code === null) {
                $customer->referral_code = strtoupper(Str::random(8));
            }

            $customer->role = 'customer';
            $customer->save();

            $this->command->info(
                "Customer seeded: {$account['email']} / {$account['password']}"
            );
        }
    }
}
