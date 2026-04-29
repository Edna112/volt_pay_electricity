<?php

namespace App\Services\Gateway;

use App\Models\Payment;
use App\Models\Transaction;
use Illuminate\Support\Str;

class MockGatewayClient implements GatewayClientInterface
{
    public function initiate(Payment $payment, Transaction $transaction): array
    {
        // Pretend the gateway created a pending charge and gave us a reference.
        $gatewayReference = 'MOCKGW-'.Str::upper(Str::random(12));

        return [
            'gateway_reference' => $gatewayReference,
            'payload' => [
                'status' => 'pending',
                'payment_id' => $payment->getKey(),
                'transaction_id' => $transaction->getKey(),
                'gateway_reference' => $gatewayReference,
                'checkout_url' => url('/mock-gateway/checkout/'.$gatewayReference),
            ],
        ];
    }
}

