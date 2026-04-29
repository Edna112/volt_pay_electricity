<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class FapshiWebhookController extends Controller
{
    public function handle(Request $request, PaymentService $payments, string $secret): JsonResponse
    {
        $expected = (string) env('FAPSHI_WEBHOOK_SECRET', '');
        if ($expected === '' || ! hash_equals($expected, $secret)) {
            Log::warning('Fapshi webhook secret mismatch', [
                'ip' => $request->ip(),
            ]);

            return response()->json(['message' => 'Invalid secret.'], 401);
        }

        // Per docs, webhook payload matches payment-status payload.
        $payload = $request->all();
        $transId = (string) ($payload['transId'] ?? '');
        $status = (string) ($payload['status'] ?? '');

        if ($transId === '' || $status === '') {
            return response()->json([
                'message' => 'transId and status are required.',
            ], 422);
        }

        $payments->handleWebhook('fapshi', $transId, Str::lower($status), $payload);

        return response()->json(['message' => 'Webhook received.']);
    }
}

