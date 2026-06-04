<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Payment;
use App\Models\WalletTopUp;
use App\Services\CacheService;
use App\Services\LesPayTopUpFeeService;
use App\Services\PaymentGatewayService;
use App\Services\WalletService;
use App\Services\WalletValidationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Mobile-app payment gateway routes (/v1/payment-gateway/*).
 */
class PaymentGatewayInvoicesController extends Controller
{
    public function __construct(private PaymentGatewayService $xendit) {}

    public function createInvoice(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'amount'              => 'required|numeric|min:10|max:100000',
            'description'         => 'required|string|max:255',
            'payment_method'      => 'required|string|in:xendit,gcash,maya,paymaya,card',
            'success_url'         => 'nullable|string|max:500',
            'cancel_url'          => 'nullable|string|max:500',
            'metadata'            => 'nullable|array',
            'metadata.type'       => 'nullable|string|in:wallet_topup,order_payment',
            'metadata.order_id'   => 'required_if:metadata.type,order_payment|integer|exists:orders,id',
        ]);

        $validated['payment_method'] = $this->normalizePaymentMethod(
            $validated['payment_method']
        );

        $meta = $validated['metadata'] ?? [];
        $type = $meta['type'] ?? 'wallet_topup';

        if ($type === 'order_payment') {
            return $this->createOrderPaymentInvoice($request, $validated, $meta);
        }

        return $this->createWalletTopUpInvoice($request, $validated, $meta);
    }

    public function getInvoice(Request $request, string $invoiceId): JsonResponse
    {
        try {
            $invoice = $this->xendit->getInvoice($invoiceId);
            $paymentMethod = 'xendit';
            $payment = null;

            $topUp = WalletTopUp::where('xendit_invoice_id', $invoiceId)
                ->where('user_id', $request->user()->id)
                ->first();

            if ($topUp) {
                $paymentMethod = $topUp->payment_method ?? 'xendit';

                if (in_array(strtoupper($invoice['status']), ['PAID', 'SETTLED'], true)) {
                    WalletService::completeTopUp($topUp);
                    $invoice['status'] = 'PAID';
                }
            } else {
                $payment = Payment::where('provider_reference', $invoiceId)
                    ->where('customer_id', $request->user()->id)
                    ->first();

                if ($payment) {
                    $paymentMethod = $payment->method ?? 'xendit';
                    $this->syncOrderPaymentFromInvoice($payment, $invoice);
                }
            }

            $payload = $this->formatInvoicePayload($invoice, $paymentMethod);
            if (!empty($payment?->order_id)) {
                $payload['order_id'] = (int) $payment->order_id;
            }

            return $this->success($payload);
        } catch (\Throwable $e) {
            return $this->error($e->getMessage(), 502);
        }
    }

    public function validateWallet(Request $request): JsonResponse
    {
        $user = $request->user();
        $wallet = WalletValidationService::ensureWalletExists($user);
        $balance = (float) $wallet->balance;
        $threshold = WalletValidationService::getMinimumThreshold();
        $isValid = $balance >= $threshold;

        return response()->json([
            'success'            => true,
            'is_valid'           => $isValid,
            'current_balance'    => $balance,
            'minimum_threshold'  => $threshold,
            'needs_topup'        => !$isValid,
            'message'            => $isValid
                ? 'Wallet balance is sufficient.'
                : 'Top up your LesPay wallet to continue.',
        ]);
    }

    public function walletThreshold(): JsonResponse
    {
        $threshold = WalletValidationService::getMinimumThreshold();

        return response()->json([
            'success'            => true,
            'minimum_threshold'  => $threshold,
            'recommended_topup'  => max($threshold, 500),
        ]);
    }

    /**
     * Mobile refund request (maps payment_id to gateway reference).
     */
    public function requestRefund(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'payment_id' => 'required_without:payment_request_id|integer|exists:payments,id',
            'payment_request_id' => 'required_without:payment_id|string|max:255',
            'amount'             => 'required|numeric|min:1',
            'reason'             => 'nullable|string|max:255',
        ]);

        $user = $request->user();
        $paymentRequestId = $validated['payment_request_id'] ?? null;

        if (!empty($validated['payment_id'])) {
            $payment = Payment::findOrFail($validated['payment_id']);

            if ((int) $payment->customer_id !== (int) $user->id && !$user->isAdmin()) {
                return $this->error('You can only refund your own payments.', 403);
            }

            $paymentRequestId = $payment->provider_reference
                ?? ($payment->meta['payment_request_id'] ?? null);

            if (!$paymentRequestId) {
                return $this->error('Payment gateway reference not found for this payment.', 422);
            }
        }

        if (!$this->xenditConfigured()) {
            return $this->error('Xendit is not configured on the server.', 503);
        }

        $reason = strtoupper($validated['reason'] ?? 'REQUESTED_BY_CUSTOMER');
        if (!in_array($reason, ['DUPLICATE', 'FRAUDULENT', 'REQUESTED_BY_CUSTOMER'], true)) {
            $reason = 'REQUESTED_BY_CUSTOMER';
        }

        try {
            $refund = $this->xendit->createRefund(
                $paymentRequestId,
                (float) $validated['amount'],
                $reason
            );

            return $this->success($refund, 'Refund request submitted successfully');
        } catch (\Throwable $e) {
            return $this->error($e->getMessage(), 502);
        }
    }

    private function createWalletTopUpInvoice(
        Request $request,
        array $validated,
        array $meta,
    ): JsonResponse {
        if (!$this->xenditConfigured()) {
            return $this->error('Xendit is not configured on the server.', 503);
        }

        $user = $request->user();
        $wallet = WalletValidationService::ensureWalletExists($user);
        $externalId = 'lespay-topup-' . $user->id . '-' . Str::uuid();
        $walletAmount = (float) ($meta['wallet_amount'] ?? $validated['amount']);
        $pricing = LesPayTopUpFeeService::calculate($walletAmount);

        $invoiceMeta = [
            'success_redirect_url' => $validated['success_url'] ?? 'lesgo://lespay/topup/success',
            'failure_redirect_url' => $validated['cancel_url'] ?? 'lesgo://lespay/topup/failure',
            'payer_email'          => $user->email,
            'payer_name'           => $user->name,
            'payer_phone'          => $user->phone_number,
            'payment_methods'      => $this->xenditPaymentMethods($validated['payment_method']),
        ];

        try {
            $invoice = $this->xendit->createInvoice(
                $pricing['total_charged'],
                $externalId,
                $validated['description'],
                $invoiceMeta,
            );

            try {
                WalletTopUp::create([
                    'user_id'           => $user->id,
                    'wallet_id'         => $wallet->id,
                    'amount'            => $pricing['wallet_amount'],
                    'fee'               => $pricing['fee'],
                    'total_charged'     => $pricing['total_charged'],
                    'currency'          => 'PHP',
                    'status'            => 'pending',
                    'payment_method'    => $validated['payment_method'],
                    'xendit_invoice_id' => $invoice['id'],
                    'external_id'       => $externalId,
                    'invoice_url'       => $invoice['invoice_url'],
                    'meta'              => array_merge($meta, [
                        'fee_rate'      => $pricing['fee_rate'],
                        'wallet_amount' => $pricing['wallet_amount'],
                        'fee'           => $pricing['fee'],
                    ]),
                ]);
            } catch (\Throwable $dbErr) {
                Log::warning('WalletTopUp persist failed after gateway invoice created', [
                    'external_id' => $externalId,
                    'invoice_id'  => $invoice['id'],
                    'error'       => $dbErr->getMessage(),
                ]);
            }

            $payload = $this->formatInvoicePayload($invoice, $validated['payment_method']);
            $payload['fee'] = $pricing['fee'];
            $payload['wallet_amount'] = $pricing['wallet_amount'];
            $payload['total_charged'] = $pricing['total_charged'];

            return $this->created(
                $payload,
                'Invoice created — open invoice_url to complete payment'
            );
        } catch (\Throwable $e) {
            return $this->error($e->getMessage(), 502);
        }
    }

    private function createOrderPaymentInvoice(
        Request $request,
        array $validated,
        array $meta,
    ): JsonResponse {
        if (!$this->xenditConfigured()) {
            return $this->error('Xendit is not configured on the server.', 503);
        }

        $user = $request->user();
        $order = Order::findOrFail((int) $meta['order_id']);

        if (!$user->isAdmin() && (int) $order->customer_id !== (int) $user->id) {
            return $this->error('You can only pay for your own orders.', 403);
        }

        if ($order->payment_status === 'paid') {
            return $this->error('Order is already paid.', 409);
        }

        $amount = round((float) $validated['amount'], 2);
        $method = $validated['payment_method'];
        $externalId = 'lesgo-order-' . $order->id . '-' . Str::random(8);

        $invoiceMeta = [
            'success_redirect_url' => $validated['success_url'] ?? 'lesgo://order/payment/success',
            'failure_redirect_url' => $validated['cancel_url'] ?? 'lesgo://order/payment/failure',
            'payer_email'          => $user->email,
            'payer_name'           => $user->name,
            'payer_phone'          => $user->phone_number,
            'payment_methods'      => $this->xenditPaymentMethods($method),
        ];

        try {
            $invoice = $this->xendit->createInvoice(
                $amount,
                $externalId,
                $validated['description'],
                $invoiceMeta,
            );

            try {
                Payment::updateOrCreate(
                    [
                        'order_id' => $order->id,
                        'status'   => 'pending',
                    ],
                    [
                        'customer_id'        => $order->customer_id,
                        'partner_id'         => $order->partner_id,
                        'amount'             => $amount,
                        'currency'           => 'PHP',
                        'method'             => $method,
                        'provider'           => 'xendit',
                        'provider_reference' => $invoice['id'],
                        'meta'               => [
                            'external_id'  => $externalId,
                            'invoice_url'  => $invoice['invoice_url'],
                            'type'         => 'order_payment',
                        ],
                    ]
                );

                $order->update([
                    'payment_method' => $method,
                    'payment_status' => 'pending',
                ]);

                CacheService::forgetByPattern("payments:user:{$order->customer_id}:list:*");
            } catch (\Throwable $dbErr) {
                Log::warning('Order payment persist failed after gateway invoice created', [
                    'order_id'    => $order->id,
                    'external_id' => $externalId,
                    'invoice_id'  => $invoice['id'],
                    'error'       => $dbErr->getMessage(),
                ]);
            }

            $payload = $this->formatInvoicePayload($invoice, $method);
            $payload['order_id'] = (int) $order->id;

            return $this->created(
                $payload,
                'Invoice created — open invoice_url to complete payment'
            );
        } catch (\Throwable $e) {
            return $this->error($e->getMessage(), 502);
        }
    }

    private function syncOrderPaymentFromInvoice(Payment $payment, array $invoice): void
    {
        $gatewayStatus = strtolower($invoice['status'] ?? 'pending');
        $isPaid = in_array(strtoupper($gatewayStatus), ['PAID', 'SETTLED', 'COMPLETED'], true);

        if ($isPaid && $payment->status !== 'paid') {
            $payment->update([
                'status'  => 'paid',
                'paid_at' => $invoice['paid_at'] ?? now(),
            ]);

            Order::where('id', $payment->order_id)->update(['payment_status' => 'paid']);
            $invoice['status'] = 'PAID';

            return;
        }

        if (in_array(strtoupper($gatewayStatus), ['EXPIRED', 'FAILED'], true)
            && $payment->status === 'pending') {
            $payment->update(['status' => 'failed']);
            Order::where('id', $payment->order_id)->update(['payment_status' => 'failed']);
        }
    }

    private function normalizePaymentMethod(string $method): string
    {
        return match (strtolower($method)) {
            'paymaya' => 'maya',
            default   => strtolower($method),
        };
    }

    /**
     * @return list<string>|null
     */
    private function xenditPaymentMethods(string $method): ?array
    {
        return match ($method) {
            'gcash'  => ['GCASH'],
            'maya'   => ['PAYMAYA'],
            'card'   => ['CREDIT_CARD', 'DEBIT_CARD'],
            default  => null,
        };
    }

    private function xenditConfigured(): bool
    {
        return PaymentGatewayService::secretKey() !== '';
    }

    private function formatInvoicePayload(array $invoice, string $paymentMethod): array
    {
        return [
            'invoice_id'     => $invoice['id'],
            'invoice_url'    => $invoice['invoice_url'] ?? null,
            'order_id'       => null,
            'amount'         => (float) ($invoice['amount'] ?? 0),
            'currency'       => $invoice['currency'] ?? 'PHP',
            'status'         => strtolower($invoice['status'] ?? 'pending'),
            'payment_method' => $paymentMethod,
            'paid_at'        => $invoice['paid_at'] ?? null,
            'expires_at'     => $invoice['expiry_date'] ?? now()->addDay()->toIso8601String(),
        ];
    }
}
