<?php

namespace App\Services;

use App\Models\DriverPackagePurchase;
use App\Models\DriverProfile;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class DriverPackageUpgradeService
{
    public function catalogFor(User $user): array
    {
        $profile = $this->requireDriverProfile($user);
        $currentTier = RiderCommissionService::normalizeTier(
            (string) ($profile->package_tier ?? RiderCommissionService::PACKAGE_BASIC)
        );

        return [
            'current_tier' => $currentTier,
            'current_label' => RiderCommissionService::labelForTier($currentTier),
            'packages' => RiderCommissionService::packageCatalog($currentTier),
        ];
    }

    public function start(User $user, string $targetTier, string $paymentMethod = 'xendit'): array
    {
        $profile = $this->requireDriverProfile($user);
        $currentTier = RiderCommissionService::normalizeTier((string) $profile->package_tier);
        $targetTier = RiderCommissionService::normalizeTier($targetTier);

        if (!RiderCommissionService::canUpgradeTo($currentTier, $targetTier)) {
            throw new \RuntimeException('This package upgrade is not available for your account.');
        }

        $amount = RiderCommissionService::getPackagePrice($targetTier);
        if ($amount <= 0) {
            throw new \RuntimeException('Package price is not configured.');
        }

        if ($paymentMethod === 'lespay') {
            return $this->purchaseWithWallet($user, $profile, $targetTier, $amount);
        }

        return $this->createXenditInvoice($user, $profile, $targetTier, $amount);
    }

    public function confirm(User $user, string $invoiceId): array
    {
        $profile = $this->requireDriverProfile($user);

        $purchase = DriverPackagePurchase::where('xendit_invoice_id', $invoiceId)
            ->where('user_id', $user->id)
            ->first();

        if (!$purchase) {
            throw new \RuntimeException('Package purchase record not found.');
        }

        if ($purchase->isPaid()) {
            return $this->responsePayload($profile->fresh(), $purchase);
        }

        $invoice = null;
        try {
            $invoice = app(PaymentGatewayService::class)->getInvoice($invoiceId);
            if (!in_array(strtoupper($invoice['status'] ?? ''), ['PAID', 'SETTLED'], true)) {
                throw new \RuntimeException('Payment is not completed yet.');
            }
        } catch (\RuntimeException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::warning('Xendit verification skipped in package upgrade confirm', [
                'invoice_id' => $invoiceId,
                'error' => $e->getMessage(),
            ]);
        }

        $this->markPaidAndApplyTier($purchase, $profile);

        return $this->responsePayload($profile->fresh(), $purchase->fresh());
    }

    private function purchaseWithWallet(
        User $user,
        DriverProfile $profile,
        string $targetTier,
        float $amount,
    ): array {
        $purchase = null;

        DB::transaction(function () use ($user, $profile, $targetTier, $amount, &$purchase) {
            $purchase = DriverPackagePurchase::create([
                'user_id' => $user->id,
                'driver_profile_id' => $profile->id,
                'target_tier' => $targetTier,
                'amount' => $amount,
                'currency' => 'PHP',
                'status' => 'pending',
                'payment_method' => 'lespay',
                'external_id' => 'lespay-package-' . $user->id . '-' . Str::uuid(),
                'meta' => ['source' => 'lespay_wallet'],
            ]);

            WalletService::debit(
                $user,
                $amount,
                'Package upgrade to ' . RiderCommissionService::labelForTier($targetTier),
                DriverPackagePurchase::class,
                $purchase->id,
            );

            $purchase->update([
                'status' => 'paid',
                'paid_at' => now(),
            ]);

            $profile->update(['package_tier' => $targetTier]);
        });

        return $this->responsePayload($profile->fresh(), $purchase);
    }

    private function createXenditInvoice(
        User $user,
        DriverProfile $profile,
        string $targetTier,
        float $amount,
    ): array {
        if (empty(config('services.xendit.secret_key'))
            && PaymentGatewayService::secretKey() === '') {
            throw new \RuntimeException('Payment gateway is not configured on the server.');
        }

        $externalId = 'lesgo-package-' . $user->id . '-' . Str::uuid();
        $label = RiderCommissionService::labelForTier($targetTier);
        $description = sprintf('LeSgo %s package — one-time upgrade (₱%s)', $label, number_format($amount, 2));

        $invoice = app(PaymentGatewayService::class)->createInvoice(
            $amount,
            $externalId,
            $description,
            [
                'success_redirect_url' => 'lesgo://package-upgrade/success',
                'failure_redirect_url' => 'lesgo://package-upgrade/failure',
                'payer_email' => $user->email,
                'payer_name' => $user->name,
                'payer_phone' => $user->phone_number,
            ]
        );

        $purchase = DriverPackagePurchase::create([
            'user_id' => $user->id,
            'driver_profile_id' => $profile->id,
            'target_tier' => $targetTier,
            'amount' => $amount,
            'currency' => 'PHP',
            'status' => 'pending',
            'payment_method' => 'xendit',
            'xendit_invoice_id' => $invoice['id'],
            'external_id' => $externalId,
            'invoice_url' => $invoice['invoice_url'] ?? null,
            'meta' => ['label' => $label],
        ]);

        return [
            'status' => 'pending_payment',
            'invoice_id' => $invoice['id'],
            'invoice_url' => $invoice['invoice_url'] ?? null,
            'amount' => $amount,
            'target_tier' => $targetTier,
            'target_label' => $label,
            'purchase_id' => $purchase->id,
            'driver_profile' => $profile->fresh(),
        ];
    }

    private function markPaidAndApplyTier(DriverPackagePurchase $purchase, DriverProfile $profile): void
    {
        DB::transaction(function () use ($purchase, $profile) {
            $locked = DriverPackagePurchase::whereKey($purchase->id)->lockForUpdate()->first();

            if ($locked->isPaid()) {
                return;
            }

            $locked->update([
                'status' => 'paid',
                'paid_at' => now(),
            ]);

            $profile->update(['package_tier' => $locked->target_tier]);
        });
    }

    private function requireDriverProfile(User $user): DriverProfile
    {
        if (!$user->isDriver()) {
            throw new \RuntimeException('Only rider accounts can manage packages.');
        }

        $profile = $user->driverProfile;
        if (!$profile) {
            throw new \RuntimeException('Driver profile not found.');
        }

        return $profile;
    }

    private function responsePayload(DriverProfile $profile, DriverPackagePurchase $purchase): array
    {
        return [
            'status' => 'completed',
            'package_tier' => $profile->package_tier,
            'package_label' => RiderCommissionService::labelForTier((string) $profile->package_tier),
            'commission_rate' => RiderCommissionService::resolveCommissionRate($profile),
            'purchase' => $purchase,
            'driver_profile' => $profile,
        ];
    }
}
