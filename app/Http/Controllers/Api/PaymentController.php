<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CreatePaymentRequest;
use App\Services\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

class PaymentController extends Controller
{
    public function store(CreatePaymentRequest $request, PaymentService $payments): JsonResponse
    {
        $idempotencyKey = $request->header('Idempotency-Key')
            ?: (string) $request->input('idempotency_key', '');

        if ($idempotencyKey === '') {
            // Generate a key if client didn't send one; client-side retries should send their own key.
            $idempotencyKey = (string) Str::uuid();
        }

        $data = $payments->createAndInitiate(
            $request->user(),
            (int) $request->validated('meter_id'),
            (float) $request->validated('amount'),
            $request->validated('reference'),
            $request->validated('gateway'),
            $idempotencyKey
        );

        return response()->json([
            'message' => 'Payment initiated.',
            'idempotency_key' => $idempotencyKey,
            'payment' => $data['payment'],
            'transaction' => $data['transaction'],
            'gateway' => $data['gateway'],
        ], 201);
    }
}

