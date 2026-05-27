<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Wallet;
use App\Models\WalletTopUp;
use App\Services\CacheService;
use App\Services\LesPayTopUpFeeService;
use App\Services\PaymentGatewayService;
use App\Services\WalletService;
use App\Services\WalletValidationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class WalletController extends Controller
{
    protected function authorizeWalletAccess(Request $request, int $userId): void
    {
        $auth = $request->user();

        if (!$auth) {
            abort(401, 'Unauthenticated');
        }

        if ((int) $auth->id !== (int) $userId && !$auth->isAdmin()) {
            abort(403, 'Forbidden');
        }
    }

    /**
     * @OA\Get(
     *     path="/api/v1/wallets/{userId}",
     *     summary="Get wallet balance for a user",
     *     tags={"Wallets"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="userId", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Wallet details",
     *         @OA\JsonContent(@OA\Property(property="data", ref="#/components/schemas/Wallet"))
     *     ),
     *     @OA\Response(response=403, ref="#/components/schemas/ErrorResponse")
     * )
     */
    public function showByUser(Request $request, int $userId): JsonResponse
    {
        $this->authorizeWalletAccess($request, $userId);

        $cacheKey = "wallets:user:{$userId}:balance";
        // Bust any stale cache so we always return fresh data.
        Cache::forget($cacheKey);

        $wallet = CacheService::remember($cacheKey, CacheService::CACHE_SHORT, function () use ($userId) {
            // Auto-create wallet on first access so the endpoint never 404/500.
            return WalletValidationService::ensureWalletExists(
                \App\Models\User::findOrFail($userId)
            );
        });

        return $this->success($wallet);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/wallets/{userId}/transactions",
     *     summary="Get wallet transactions for a user",
     *     tags={"Wallets"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="userId", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="type", in="query", required=false, @OA\Schema(type="string", enum={"credit","debit"})),
     *     @OA\Response(response=200, description="List of transactions",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/WalletTransaction"))
     *         )
     *     ),
     *     @OA\Response(response=403, ref="#/components/schemas/ErrorResponse"),
     *     @OA\Response(response=422, ref="#/components/schemas/ErrorResponse")
     * )
     */
    public function transactionsByUser(Request $request, int $userId): JsonResponse
    {
        $this->authorizeWalletAccess($request, $userId);

        $type = $request->query('type');

        if ($type && !in_array($type, ['credit', 'debit'], true)) {
            return $this->error('Invalid type. Allowed: credit, debit', 422);
        }

        $cacheKey = "wallets:user:{$userId}:transactions:" . ($type ?? 'all');

        $transactions = CacheService::remember($cacheKey, CacheService::CACHE_SHORT, function () use ($userId, $type) {
            $wallet = WalletValidationService::ensureWalletExists(
                \App\Models\User::findOrFail($userId)
            );
            return $wallet->transactions()
                ->when($type, fn ($q) => $q->where('type', $type))
                ->select('id', 'wallet_id', 'type', 'amount', 'description', 'reference', 'created_at')
                ->orderByDesc('id')
                ->get();
        });

        return $this->success($transactions);
    }

    public function myWallet(Request $request): JsonResponse
    {
        return $this->showByUser($request, (int) $request->user()->id);
    }

    public function myTransactions(Request $request): JsonResponse
    {
        return $this->transactionsByUser($request, (int) $request->user()->id);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/wallets/my/validation",
     *     summary="Check wallet balance validation for current user",
     *     tags={"Wallets"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(response=200, description="Wallet validation details",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="has_sufficient_balance", type="boolean"),
     *                 @OA\Property(property="current_balance", type="number", format="float"),
     *                 @OA\Property(property="minimum_threshold", type="number", format="float"),
     *                 @OA\Property(property="shortfall", type="number", format="float")
     *             )
     *         )
     *     )
     * )
     */
    public function myWalletValidation(Request $request): JsonResponse
    {
        $user = $request->user();
        $validation = WalletValidationService::validateBalance($user);

        return $this->success($validation);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/wallets/threshold",
     *     summary="Get minimum wallet balance threshold",
     *     tags={"Wallets"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(response=200, description="Minimum threshold",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="minimum_threshold", type="number", format="float")
     *             )
     *         )
     *     )
     * )
     */
    public function getThreshold(Request $request): JsonResponse
    {
        $threshold = WalletValidationService::getMinimumThreshold();

        return $this->success([
            'minimum_threshold' => $threshold
        ]);
    }

    /**
     * Create a Xendit top-up invoice or confirm a completed top-up.
     */
    public function topUp(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'amount'             => 'required_without:xendit_invoice_id|numeric|min:10|max:100000',
            'payment_method'     => 'nullable|string|in:xendit,gcash,maya,card',
            'xendit_invoice_id'  => 'nullable|string',
            'external_reference' => 'nullable|string',
        ]);

        $user = $request->user();

        if (!empty($validated['xendit_invoice_id'])) {
            return $this->confirmTopUp($request, $validated['xendit_invoice_id']);
        }

        if (empty(config('services.xendit.secret_key'))) {
            return $this->error('Xendit is not configured on the server.', 503);
        }

        $wallet = WalletValidationService::ensureWalletExists($user);
        $externalId = $validated['external_reference'] ?? ('lespay-topup-' . $user->id . '-' . Str::uuid());
        $walletAmount = (float) $validated['amount'];
        $method = $validated['payment_method'] ?? 'xendit';
        $pricing = LesPayTopUpFeeService::calculate($walletAmount);

        $xendit = app(PaymentGatewayService::class);
        $description = sprintf(
            'LesPay Top-up ₱%s + %s%% fee (₱%s)',
            number_format($pricing['wallet_amount'], 2),
            (int) ($pricing['fee_rate'] * 100),
            number_format($pricing['fee'], 2),
        );

        try {
            $invoice = $xendit->createInvoice($pricing['total_charged'], $externalId, $description, [
                'success_redirect_url' => 'lesgo://lespay/topup/success',
                'failure_redirect_url' => 'lesgo://lespay/topup/failure',
                'payer_email'          => $user->email,
                'payer_name'           => $user->name,
                'payer_phone'          => $user->phone_number,
            ]);

            WalletTopUp::updateOrCreate(
                ['external_id' => $externalId],
                [
                    'user_id'           => $user->id,
                    'wallet_id'         => $wallet->id,
                    'amount'            => $pricing['wallet_amount'],
                    'fee'               => $pricing['fee'],
                    'total_charged'     => $pricing['total_charged'],
                    'currency'          => 'PHP',
                    'status'            => 'pending',
                    'payment_method'    => $method,
                    'xendit_invoice_id' => $invoice['id'],
                    'invoice_url'       => $invoice['invoice_url'],
                    'meta'              => ['fee_rate' => $pricing['fee_rate']],
                ]
            );

            CacheService::forgetByPattern("wallets:user:{$user->id}:*");

            return $this->created([
                'invoice_id'     => $invoice['id'],
                'invoice_url'    => $invoice['invoice_url'],
                'amount'         => $pricing['wallet_amount'],
                'fee'            => $pricing['fee'],
                'total_charged'  => $pricing['total_charged'],
                'fee_rate'       => $pricing['fee_rate'],
                'status'         => strtolower($invoice['status'] ?? 'pending'),
            ], 'Top-up invoice created');
        } catch (\Throwable $e) {
            return $this->error($e->getMessage(), 502);
        }
    }

    public function withdraw(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'amount'              => 'required|numeric|min:10',
            'withdrawal_method'   => 'required|string|max:50',
        ]);

        $user = $request->user();

        try {
            WalletService::debit(
                $user,
                (float) $validated['amount'],
                'Withdrawal via ' . $validated['withdrawal_method'],
                'withdrawal',
                null,
            );

            return $this->success(
                WalletValidationService::ensureWalletExists($user)->fresh(),
                'Withdrawal submitted'
            );
        } catch (\Throwable $e) {
            return $this->error($e->getMessage(), 422);
        }
    }

    private function confirmTopUp(Request $request, string $invoiceId): JsonResponse
    {
        $user = $request->user();
        $topUp = WalletTopUp::where('xendit_invoice_id', $invoiceId)
            ->where('user_id', $user->id)
            ->first();

        try {
            $invoice = app(PaymentGatewayService::class)->getInvoice($invoiceId);
            if (!in_array(strtoupper($invoice['status']), ['PAID', 'SETTLED'], true)) {
                return $this->error('Payment is not completed yet.', 409);
            }

            if (!$topUp) {
                $wallet = WalletValidationService::ensureWalletExists($user);
                $paidTotal = (float) ($invoice['amount'] ?? 0);
                $walletAmount = round($paidTotal / (1 + LesPayTopUpFeeService::rate()), 2);
                $pricing = LesPayTopUpFeeService::calculate($walletAmount);

                $topUp = WalletTopUp::create([
                    'user_id'           => $user->id,
                    'wallet_id'         => $wallet->id,
                    'amount'            => $pricing['wallet_amount'],
                    'fee'               => $pricing['fee'],
                    'total_charged'     => $paidTotal,
                    'currency'          => 'PHP',
                    'status'            => 'pending',
                    'payment_method'    => 'xendit',
                    'xendit_invoice_id' => $invoiceId,
                    'external_id'       => $invoice['external_id'] ?? ('lespay-sync-' . $invoiceId),
                    'invoice_url'       => $invoice['invoice_url'] ?? null,
                ]);
            }

            if ($topUp->isPaid()) {
                return $this->success(
                    WalletValidationService::ensureWalletExists($user),
                    'Top-up already completed'
                );
            }

            WalletService::completeTopUp($topUp);
            CacheService::forgetByPattern("wallets:user:{$user->id}:*");

            return $this->success(
                WalletValidationService::ensureWalletExists($user)->fresh(),
                'Top-up completed'
            );
        } catch (\Throwable $e) {
            return $this->error($e->getMessage(), 502);
        }
    }
}
