<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TransactionController extends Controller
{
    /**
     * Get a transaction by gateway transId (gateway_reference).
     * Authenticated: user can only access their own transactions.
     */
    public function showByTransId(Request $request, string $transId): JsonResponse
    {
        $user = $request->user();

        /** @var Transaction|null $tx */
        $tx = Transaction::query()
            ->where('gateway_reference', $transId)
            ->whereHas('payment', function ($q) use ($user) {
                $q->where('user_id', $user->getKey());
            })
            ->with('payment')
            ->first();

        if (! $tx) {
            return response()->json([
                'message' => 'Transaction not found.',
            ], 404);
        }

        return response()->json([
            'transaction' => $tx,
            'payment' => $tx->payment,
        ]);
    }
}

