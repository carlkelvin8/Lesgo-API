<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Wallet;
use App\Services\CacheService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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

    public function showByUser(Request $request, int $userId): JsonResponse
    {
        $this->authorizeWalletAccess($request, $userId);

        $cacheKey = "wallets:user:{$userId}:balance";

        $wallet = CacheService::remember($cacheKey, CacheService::CACHE_SHORT, function () use ($userId) {
            return Wallet::where('user_id', $userId)->firstOrFail();
        });

        return $this->success($wallet);
    }

    public function transactionsByUser(Request $request, int $userId): JsonResponse
    {
        $this->authorizeWalletAccess($request, $userId);

        $type = $request->query('type');

        if ($type && !in_array($type, ['credit', 'debit'], true)) {
            return $this->error('Invalid type. Allowed: credit, debit', 422);
        }

        $cacheKey = "wallets:user:{$userId}:transactions:" . ($type ?? 'all');

        $transactions = CacheService::remember($cacheKey, CacheService::CACHE_SHORT, function () use ($userId, $type) {
            $wallet = Wallet::where('user_id', $userId)
                ->with(['transactions' => function ($q) use ($type) {
                    if ($type) {
                        $q->where('type', $type);
                    }
                    $q->orderByDesc('id');
                }])
                ->firstOrFail();

            return $wallet->transactions;
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
}
