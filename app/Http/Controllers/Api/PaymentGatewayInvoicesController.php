<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\WalletTopUp;
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
            'amount'          => 'required|numeric|min:10|max:100000',
            'description'     => 'required|string|max:255',
            'payment_method'  => 'required|string|in:xendit,gcash,maya,card',
            'success_url'     => 'nullable|url',
            'cancel_url'      => 'nullable|url',
            'metadata'        => 'nullable|array',
            'metadata.type'   => 'nullable|string',
        ]);

        $user = $request->user();
        $meta = $validated['metadata'] ?? [];
        $type = $meta['type'] ?? 'wallet_topup';

        if ($type !== 'wallet_topup') {
            return $this->error('Only wallet top-up invoices are supported on this endpoint.', 422);
        }

        if (empty(config('services.xendit.secret_key'))) {
            return $this->error('Xendit is not configured on the server.', 503);
        }

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

    public function getInvoice(Request $request, string $invoiceId): JsonResponse
    {
        try {
            $invoice = $this->xendit->getInvoice($invoiceId);

            $topUp = WalletTopUp::where('xendit_invoice_id', $invoiceId)
                ->where('user_id', $request->user()->id)
                ->first();

            if ($topUp && in_array(strtoupper($invoice['status']), ['PAID', 'SETTLED'], true)) {
                WalletService::completeTopUp($topUp);
                $invoice['status'] = 'PAID';
            }

            return $this->success(
                $this->formatInvoicePayload($invoice, $topUp?->payment_method ?? 'xendit')
            );
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

        if (empty(config('services.xendit.secret_key'))) {
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
}
