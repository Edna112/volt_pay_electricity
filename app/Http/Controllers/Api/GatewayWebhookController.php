<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Gateway\WebhookSignatureVerifier;
use App\Services\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class GatewayWebhookController extends Controller
{
    /**
     * Dummy webhook handler (mock gateway).
     * Replace with real gateway signature verification + real payload mapping later.
     */
    public function handle(Request $request, PaymentService $payments, WebhookSignatureVerifier $verifier): JsonResponse
    {
        $gateway = (string) $request->input('gateway', 'mock_gateway');
        $gatewayReference = (string) $request->input('gateway_reference', '');
        $status = (string) $request->input('status', 'success');

        $gatewayNormalized = Str::lower($gateway);

        // Dummy signature verification for MTN/Orange.
        if (in_array($gatewayNormalized, ['mtn', 'orange'], true)) {
            $ok = $verifier->verify($request, $gatewayNormalized);

            if (! $ok) {
                Log::warning('Webhook signature verification failed', [
                    'gateway' => $gatewayNormalized,
                    'gateway_reference' => $gatewayReference,
                    'ip' => $request->ip(),
                ]);

                return response()->json([
                    'message' => 'Invalid signature.',
                ], 401);
            }

            Log::info('Webhook signature verified', [
                'gateway' => $gatewayNormalized,
                'gateway_reference' => $gatewayReference,
            ]);
        }

        if ($gatewayReference === '') {
            return response()->json([
                'message' => 'gateway_reference is required',
            ], 422);
        }

        // Dummy payload for now (store full payload for debugging / auditing).
        $payload = $request->all();

        $payments->handleWebhook($gatewayNormalized, $gatewayReference, $status, $payload);

        return response()->json([
            'message' => 'Webhook received.',
        ]);
    }
}

