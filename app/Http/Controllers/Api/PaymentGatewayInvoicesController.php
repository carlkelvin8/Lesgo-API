<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\WalletTopUp;
use App\Services\PaymentGatewayService;
use App\Services\WalletService;
use App\Services\WalletValidationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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

        $invoiceMeta = [
            'success_redirect_url' => $validated['success_url'] ?? 'lesgo://lespay/topup/success',
            'failure_redirect_url' => $validated['cancel_url'] ?? 'lesgo://lespay/topup/failure',
            'payer_email'          => $user->email,
            'payer_name'           => $user->name,
            'payer_phone'          => $user->phone_number,
        ];

        try {
            $invoice = $this->xendit->createInvoice(
                (float) $validated['amount'],
                $externalId,
                $validated['description'],
                $invoiceMeta,
            );

            WalletTopUp::create([
                'user_id'           => $user->id,
                'wallet_id'         => $wallet->id,
                'amount'            => $validated['amount'],
                'currency'          => 'PHP',
                'status'            => 'pending',
                'payment_method'    => $validated['payment_method'],
                'xendit_invoice_id' => $invoice['id'],
                'external_id'       => $externalId,
                'invoice_url'       => $invoice['invoice_url'],
                'meta'              => $meta,
            ]);

            return $this->created(
                $this->formatInvoicePayload($invoice, $validated['payment_method']),
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
}
