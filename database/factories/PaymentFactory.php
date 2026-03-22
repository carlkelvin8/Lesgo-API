<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class PaymentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'order_id'    => Order::factory(),
            'customer_id' => User::factory(),
            'amount'      => fake()->randomFloat(2, 40, 500),
            'currency'    => 'PHP',
            'method'      => fake()->randomElement(['cash', 'gcash', 'maya', 'xendit']),
            'status'      => 'pending',
        ];
    }

    public function paid(): static
    {
        return $this->state(['status' => 'paid', 'paid_at' => now()]);
    }

    public function failed(): static
    {
        return $this->state(['status' => 'failed']);
    }
}
