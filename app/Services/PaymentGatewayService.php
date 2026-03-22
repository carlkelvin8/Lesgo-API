<?php

namespace App\Services;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PaymentGatewayService
{
    private string $baseUrl;
    private string $secretKey;
    private string $publicKey;

    public function __construct()
    {
        $this->baseUrl   = config('services.paymongo.base_url', 'https://api.paymongo.com/v1');
        $this->secretKey = config('services.paymongo.secret_key', '');
        $this->publicKey = config('services.paymongo.public_key', '');
    }

    /**
     * Create a PayMongo payment link.
     */
    public function createPaymentLink(float $amount, string $description, array $meta = []): array
    {
        $response = $this->request('POST', '/links', [
            'data' => [
                'attributes' => [
                    'amount'      => (int) round($amount * 100), // centavos
                    'description' => $description,
                    'remarks'     => $meta['remarks'] ?? null,
                ],
            ],
        ]);

        if (!$response->successful()) {
            Log::error('PayMongo createPaymentLink failed', [
                'status' => $response->status(),
                'body'   => $response->json(),
            ]);
            throw new \RuntimeException('Failed to create payment link: ' . $response->body());
        }

        $data = $response->json('data');

        return [
            'id'          => $data['id'],
            'checkout_url' => $data['attributes']['checkout_url'],
            'reference'   => $data['attributes']['reference_number'],
            'status'      => $data['attributes']['status'],
            'amount'      => $data['attributes']['amount'] / 100,
        ];
    }

    /**
     * Retrieve a payment intent or link by ID.
     */
    public function retrieve(string $id, string $type = 'links'): array
    {
        $response = $this->request('GET', "/{$type}/{$id}");

        if (!$response->successful()) {
            throw new \RuntimeException("Failed to retrieve PayMongo {$type}: " . $response->body());
        }

        return $response->json('data');
    }

    /**
     * Create a refund for a payment.
     */
    public function refund(string $paymentId, float $amount, string $reason = 'others'): array
    {
        $response = $this->request('POST', '/refunds', [
            'data' => [
                'attributes' => [
                    'amount'     => (int) round($amount * 100),
                    'payment_id' => $paymentId,
                    'reason'     => $reason, // duplicate, fraudulent, others
                    'notes'      => 'Refund via LeSGo API',
                ],
            ],
        ]);

        if (!$response->successful()) {
            Log::error('PayMongo refund failed', [
                'payment_id' => $paymentId,
                'status'     => $response->status(),
                'body'       => $response->json(),
            ]);
            throw new \RuntimeException('Refund failed: ' . $response->body());
        }

        return $response->json('data');
    }

    /**
     * Verify a PayMongo webhook signature.
     */
    public function verifyWebhookSignature(string $payload, string $signature, string $secret): bool
    {
        // PayMongo uses: t=timestamp,te=test_signature,li=live_signature
        $parts = [];
        foreach (explode(',', $signature) as $part) {
            [$key, $value] = explode('=', $part, 2);
            $parts[$key] = $value;
        }

        $timestamp = $parts['t'] ?? '';
        $sigKey    = app()->environment('production') ? 'li' : 'te';
        $received  = $parts[$sigKey] ?? '';

        $expected = hash_hmac('sha256', "{$timestamp}.{$payload}", $secret);

        return hash_equals($expected, $received);
    }

    private function request(string $method, string $path, array $body = []): Response
    {
        $request = Http::withBasicAuth($this->secretKey, '')
            ->withHeaders(['Content-Type' => 'application/json'])
            ->timeout(30);

        return match (strtoupper($method)) {
            'POST'  => $request->post($this->baseUrl . $path, $body),
            'GET'   => $request->get($this->baseUrl . $path),
            'DELETE' => $request->delete($this->baseUrl . $path),
            default => throw new \InvalidArgumentException("Unsupported method: {$method}"),
        };
    }
}
