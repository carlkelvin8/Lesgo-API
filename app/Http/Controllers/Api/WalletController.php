<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Http\Request;

/**
 * @OA\Schema(
 *     schema="Wallet",
 *     type="object",
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="user_id", type="integer", example=10),
 *     @OA\Property(property="balance", type="number", format="float", example=500.00),
 *     @OA\Property(property="currency", type="string", example="PHP")
 * )
 *
 * @OA\Schema(
 *     schema="WalletTransaction",
 *     type="object",
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="wallet_id", type="integer", example=1),
 *     @OA\Property(property="type", type="string", example="credit"),
 *     @OA\Property(property="source_type", type="string", example="order"),
 *     @OA\Property(property="source_id", type="integer", example=123),
 *     @OA\Property(property="amount", type="number", format="float", example=250.00),
 *     @OA\Property(property="balance_before", type="number", format="float", example=100.00),
 *     @OA\Property(property="balance_after", type="number", format="float", example=350.00),
 *     @OA\Property(property="description", type="string", example="Earnings from completed trip")
 * )
 */
class WalletController extends Controller
{
    /**
     * Get wallet by user ID.
     *
     * @OA\Get(
     *     path="/api/v1/wallets/{user_id}",
     *     summary="Get wallet balance for a user",
     *     tags={"Wallets"},
     *     @OA\Parameter(
     *         name="user_id",
     *         in="path",
     *         required=true,
     *         description="User ID",
     *         @OA\Schema(type="integer", example=10)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Wallet details",
     *         @OA\JsonContent(ref="#/components/schemas/Wallet")
     *     ),
     *     @OA\Response(response=404, description="Not found")
     * )
     */
    public function showByUser(int $userId)
    {
        $wallet = Wallet::where('user_id', $userId)->firstOrFail();

        return response()->json($wallet);
    }

    /**
     * List wallet transactions for a user.
     *
     * @OA\Get(
     *     path="/api/v1/wallets/{user_id}/transactions",
     *     summary="List wallet transactions for a user",
     *     tags={"Wallets"},
     *     @OA\Parameter(
     *         name="user_id",
     *         in="path",
     *         required=true,
     *         description="User ID",
     *         @OA\Schema(type="integer", example=10)
     *     ),
     *     @OA\Parameter(
     *         name="type",
     *         in="query",
     *         required=false,
     *         description="Filter by transaction type (credit/debit)",
     *         @OA\Schema(type="string", example="credit")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="List of wallet transactions",
     *         @OA\JsonContent(
     *             type="array",
     *             @OA\Items(ref="#/components/schemas/WalletTransaction")
     *         )
     *     )
     * )
     */
    public function transactionsByUser(Request $request, int $userId)
    {
        $wallet = Wallet::where('user_id', $userId)
            ->with(['transactions' => function ($q) use ($request) {
                if ($type = $request->query('type')) {
                    $q->where('type', $type);
                }
                $q->orderByDesc('id');
            }])
            ->firstOrFail();

        return response()->json($wallet->transactions);
    }
}
