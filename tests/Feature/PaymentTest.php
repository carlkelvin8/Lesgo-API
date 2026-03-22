<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Payment;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentTest extends TestCase
{
    use RefreshDatabase;

    private function createOrderForCustomer(User $customer): Order
    {
        $service = Service::factory()->create();
        return Order::factory()->create(['customer_id' => $customer->id, 'service_id' => $service->id]);
    }

    // ── List Payments ─────────────────────────────────────────────────────────

    public function test_customer_sees_only_own_payments(): void
    {
        $customer = $this->actingAsRole('customer');
        $other    = User::factory()->create(['role' => 'customer']);

        $order1 = $this->createOrderForCustomer($customer);
        $order2 = $this->createOrderForCustomer($other);

        Payment::factory()->create(['customer_id' => $customer->id, 'order_id' => $order1->id]);
        Payment::factory()->create(['customer_id' => $other->id, 'order_id' => $order2->id]);

        $response = $this->getJson('/api/v1/payments');
        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
    }

    public function test_admin_sees_all_payments(): void
    {
        $c1 = User::factory()->create(['role' => 'customer']);
        $c2 = User::factory()->create(['role' => 'customer']);

        Payment::factory()->create(['customer_id' => $c1->id, 'order_id' => $this->createOrderForCustomer($c1)->id]);
        Payment::factory()->create(['customer_id' => $c2->id, 'order_id' => $this->createOrderForCustomer($c2)->id]);

        $this->actingAsRole('admin');

        $response = $this->getJson('/api/v1/payments');
        $response->assertStatus(200);
        $this->assertCount(2, $response->json('data'));
    }

    // ── Store Payment ─────────────────────────────────────────────────────────

    public function test_customer_can_record_payment_for_own_order(): void
    {
        $customer = $this->actingAsRole('customer');
        $order    = $this->createOrderForCustomer($customer);

        $response = $this->postJson('/api/v1/payments', [
            'order_id'    => $order->id,
            'customer_id' => $customer->id,
            'amount'      => 150.00,
            'method'      => 'cash',
        ]);

        $response->assertStatus(201)
                 ->assertJsonPath('data.status', 'pending');

        $this->assertDatabaseHas('payments', ['order_id' => $order->id, 'customer_id' => $customer->id]);
    }

    public function test_customer_cannot_record_payment_for_another_customers_order(): void
    {
        $customer = $this->actingAsRole('customer');
        $other    = User::factory()->create(['role' => 'customer']);
        $order    = $this->createOrderForCustomer($other);

        $this->postJson('/api/v1/payments', [
            'order_id'    => $order->id,
            'customer_id' => $customer->id,
            'amount'      => 150.00,
            'method'      => 'cash',
        ])->assertStatus(403);
    }

    public function test_duplicate_paid_payment_returns_409(): void
    {
        $customer = $this->actingAsRole('customer');
        $order    = $this->createOrderForCustomer($customer);

        Payment::factory()->paid()->create([
            'order_id'    => $order->id,
            'customer_id' => $customer->id,
        ]);

        $this->postJson('/api/v1/payments', [
            'order_id'    => $order->id,
            'customer_id' => $customer->id,
            'amount'      => 150.00,
            'method'      => 'cash',
        ])->assertStatus(409);
    }

    // ── Show Payment ──────────────────────────────────────────────────────────

    public function test_customer_can_view_own_payment(): void
    {
        $customer = $this->actingAsRole('customer');
        $order    = $this->createOrderForCustomer($customer);
        $payment  = Payment::factory()->create(['customer_id' => $customer->id, 'order_id' => $order->id]);

        $this->getJson("/api/v1/payments/{$payment->id}")
             ->assertStatus(200)
             ->assertJsonPath('data.id', $payment->id);
    }

    public function test_customer_cannot_view_other_customers_payment(): void
    {
        $this->actingAsRole('customer');
        $other   = User::factory()->create(['role' => 'customer']);
        $order   = $this->createOrderForCustomer($other);
        $payment = Payment::factory()->create(['customer_id' => $other->id, 'order_id' => $order->id]);

        $this->getJson("/api/v1/payments/{$payment->id}")->assertStatus(403);
    }

    public function test_payment_requires_authentication(): void
    {
        $this->getJson('/api/v1/payments')->assertStatus(401);
    }
}
