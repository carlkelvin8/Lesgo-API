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

        $wallet = CacheService::remember($cacheKey, CacheService::CACHE_SHORT, function () use ($userId) {
            return Wallet::where('user_id', $userId)->firstOrFail();
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
            $wallet = Wallet::where('user_id', $userId)
                ->with(['transactions' => function ($q) use ($type) {
                    if ($type) {
                        $q->where('type', $type);
                    }
                    $q->select('id', 'wallet_id', 'type', 'amount', 'description', 'reference', 'created_at')
                      ->orderByDesc('id');
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
