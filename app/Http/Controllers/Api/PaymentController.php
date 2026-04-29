<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CreatePaymentRequest;
use App\Http\Requests\FapshiDirectPayRequest;
use App\Services\Gateway\FapshiGatewayClient;
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

    /**
     * Fapshi Direct Pay initiation.
     */
    public function directPay(
        FapshiDirectPayRequest $request,
        PaymentService $payments,
        FapshiGatewayClient $fapshi
    ): JsonResponse {
        $idempotencyKey = $request->header('Idempotency-Key')
            ?: (string) $request->input('idempotency_key', '');

        if ($idempotencyKey === '') {
            $idempotencyKey = (string) Str::uuid();
        }

        $gatewayMeta = [
            'phone' => $request->validated('phone'),
            'medium' => $request->validated('medium', null),
            'name' => $request->validated('name', null),
            'email' => $request->validated('email', null),
            'message' => $request->validated('message', null),
        ];

        $data = $payments->createAndInitiateWithGatewayClient(
            $request->user(),
            (int) $request->validated('meter_id'),
            (float) $request->validated('amount'),
            $request->validated('reference'),
            'fapshi',
            $idempotencyKey,
            $fapshi,
            $gatewayMeta
        );

        return response()->json([
            'message' => 'Fapshi direct pay initiated.',
            'idempotency_key' => $idempotencyKey,
            'payment' => $data['payment'],
            'transaction' => $data['transaction'],
            'gateway' => $data['gateway'],
        ], 201);
    }
}

