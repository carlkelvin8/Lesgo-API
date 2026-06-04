<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Xendit\Configuration;
use Xendit\Invoice\CreateInvoiceRequest;
use Xendit\Invoice\InvoiceApi;
use Xendit\Payout\CreatePayoutRequest;
use Xendit\Payout\PayoutApi;
use Xendit\Refund\CreateRefund;
use Xendit\Refund\RefundApi;
use Xendit\Invoice\Invoice;

class PaymentGatewayService
{
    /** Xendit development key — used when XENDIT_SECRET_KEY env is not set. */
    public const DEV_KEY = 'xnd_development_0oY0lkbRUCEtOJS4tI8Z3emBEy4iY0qpi8GqHa3PaJ4doJapAQqWBWC6oFbjkAQ';

    public static function secretKey(): string
    {
        return config('services.xendit.secret_key') ?: self::DEV_KEY;
    }

    public function __construct()
    {
        Configuration::setXenditKey(self::secretKey());
    }

    // -------------------------------------------------------------------------
    // Invoice (hosted checkout — supports GCash, Maya, cards, OTC, VA, etc.)
    // -------------------------------------------------------------------------

    /**
     * Create a Xendit Invoice (hosted payment page).
     *
     * @param  float  $amount       Amount in PHP (e.g. 150.00)
     * @param  string $externalId   Your unique reference (e.g. "order-42")
     * @param  string $description  Shown on the payment page
     * @param  array  $meta         Optional: payer_email, payer_name, success_redirect_url, failure_redirect_url, items
     */
    public function createInvoice(float $amount, string $externalId, string $description, array $meta = []): array
    {
        $api = new InvoiceApi();

        $request = new CreateInvoiceRequest([
            'external_id'          => $externalId,
            'amount'               => $amount,
            'description'          => $description,
            'currency'             => $meta['currency'] ?? 'PHP',
            'invoice_duration'     => $meta['invoice_duration'] ?? 86400, // 24h default
            'success_redirect_url' => $meta['success_redirect_url'] ?? config('services.xendit.success_url'),
            'failure_redirect_url' => $meta['failure_redirect_url'] ?? config('services.xendit.failure_url'),
            'payer_email'          => $meta['payer_email'] ?? null,
            'customer'             => isset($meta['payer_name']) ? [
                'given_names' => $meta['payer_name'],
                'email'       => $meta['payer_email'] ?? null,
                'mobile_number' => $meta['payer_phone'] ?? null,
            ] : null,
            'items'                => $meta['items'] ?? null,
            'payment_methods'      => $meta['payment_methods'] ?? null, // restrict to specific methods
        ]);

        try {
            /** @var Invoice $invoice */
            $invoice = $api->createInvoice($request);

            return [
                'id'           => $invoice->getId(),
                'external_id'  => $invoice->getExternalId(),
                'invoice_url'  => $invoice->getInvoiceUrl(),
                'status'       => $invoice->getStatus(),
                'amount'       => $invoice->getAmount(),
                'currency'     => $invoice->getCurrency(),
                'expiry_date'  => $invoice->getExpiryDate()?->format('c'),
            ];
        } catch (\Xendit\XenditSdkException $e) {
            Log::error('Xendit createInvoice failed', [
                'error'      => $e->getMessage(),
                'full_error' => $e->getFullError(),
                'external_id' => $externalId,
            ]);
            throw new \RuntimeException('Failed to create Xendit invoice: ' . $e->getMessage());
        }
    }

    /**
     * Retrieve a Xendit Invoice by ID.
     */
    public function getInvoice(string $invoiceId): array
    {
        $api = new InvoiceApi();

        try {
            $invoice = $api->getInvoiceById($invoiceId);

            return [
                'id'          => $invoice->getId(),
                'external_id' => $invoice->getExternalId(),
                'invoice_url' => $invoice->getInvoiceUrl(),
                'status'      => $invoice->getStatus(),
                'amount'      => $invoice->getAmount(),
                'currency'    => $invoice->getCurrency(),
                'paid_amount' => $invoice->getPaidAmount(),
                'paid_at'     => $invoice->getPaidAt()?->format('c'),
                'payment_method' => $invoice->getPaymentMethod(),
            ];
        } catch (\Xendit\XenditSdkException $e) {
            Log::error('Xendit getInvoice failed', ['invoice_id' => $invoiceId, 'error' => $e->getMessage()]);
            throw new \RuntimeException('Failed to retrieve invoice: ' . $e->getMessage());
        }
    }

    /**
     * Expire (cancel) a pending Xendit Invoice.
     */
    public function expireInvoice(string $invoiceId): array
    {
        $api = new InvoiceApi();

        try {
            $invoice = $api->expireInvoice($invoiceId);
            return ['id' => $invoice->getId(), 'status' => $invoice->getStatus()];
        } catch (\Xendit\XenditSdkException $e) {
            Log::error('Xendit expireInvoice failed', ['invoice_id' => $invoiceId, 'error' => $e->getMessage()]);
            throw new \RuntimeException('Failed to expire invoice: ' . $e->getMessage());
        }
    }

    // -------------------------------------------------------------------------
    // Payouts (withdraw to GCash / Maya)
    // -------------------------------------------------------------------------

    /**
     * Send funds to a GCash or Maya account via Xendit Payouts API.
     */
    public function createPayout(
        float $amount,
        string $referenceId,
        string $channelCode,
        string $accountHolderName,
        string $accountNumber,
        ?string $description = null,
    ): array {
        $api = new PayoutApi();
        $idempotencyKey = (string) Str::uuid();

        $request = new CreatePayoutRequest([
            'reference_id'       => $referenceId,
            'currency'           => 'PHP',
            'channel_code'       => $channelCode,
            'channel_properties' => [
                'account_holder_name' => $accountHolderName,
                'account_number'      => $accountNumber,
            ],
            'amount'             => $amount,
            'description'        => $description ?? 'LesGo wallet withdrawal',
        ]);

        try {
            $payout = $api->createPayout($idempotencyKey, null, $request);

            return [
                'id'           => $payout->getId(),
                'reference_id' => $payout->getReferenceId(),
                'status'       => $payout->getStatus(),
                'amount'       => $payout->getAmount(),
                'currency'     => $payout->getCurrency(),
                'channel_code' => $payout->getChannelCode(),
            ];
        } catch (\Xendit\XenditSdkException $e) {
            Log::error('Xendit createPayout failed', [
                'reference_id' => $referenceId,
                'channel_code' => $channelCode,
                'error'        => $e->getMessage(),
                'full_error'   => $e->getFullError(),
            ]);
            throw new \RuntimeException('Failed to send payout: ' . $e->getMessage());
        }
    }

    // -------------------------------------------------------------------------
    // Refunds
    // -------------------------------------------------------------------------

    /**
     * Create a refund for a Xendit payment.
     *
     * @param  string $paymentRequestId  The Xendit payment_request_id or payment_id
     * @param  float  $amount            Amount to refund in PHP
     * @param  string $reason            DUPLICATE | FRAUDULENT | REQUESTED_BY_CUSTOMER
     */
    public function createRefund(string $paymentRequestId, float $amount, string $reason = 'REQUESTED_BY_CUSTOMER'): array
    {
        $api = new RefundApi();

        $idempotencyKey = (string) Str::uuid();

        $refundRequest = new CreateRefund([
            'payment_request_id' => $paymentRequestId,
            'amount'             => $amount,
            'reason'             => strtoupper($reason),
            'currency'           => 'PHP',
        ]);

        try {
            $refund = $api->createRefund($idempotencyKey, null, $refundRequest);

            return [
                'id'                 => $refund->getId(),
                'payment_request_id' => $refund->getPaymentRequestId(),
                'amount'             => $refund->getRefundFeeAmount() !== null
                    ? $amount - $refund->getRefundFeeAmount()
                    : $amount,
                'status'             => $refund->getStatus(),
                'reason'             => $refund->getReason(),
                'created'            => $refund->getCreated()?->format('c'),
            ];
        } catch (\Xendit\XenditSdkException $e) {
            Log::error('Xendit createRefund failed', [
                'payment_request_id' => $paymentRequestId,
                'error'              => $e->getMessage(),
                'full_error'         => $e->getFullError(),
            ]);
            throw new \RuntimeException('Refund failed: ' . $e->getMessage());
        }
    }

    // -------------------------------------------------------------------------
    // Webhook verification
    // -------------------------------------------------------------------------

    /**
     * Verify Xendit webhook callback token.
     * Xendit sends X-CALLBACK-TOKEN header — compare against your dashboard token.
     */
    public function verifyWebhookToken(string $token): bool
    {
        $expected = config('services.xendit.webhook_token', '');

        if (empty($expected)) {
            Log::warning('Xendit webhook token not configured — skipping verification');
            return true;
        }

        return hash_equals($expected, $token);
    }
}
