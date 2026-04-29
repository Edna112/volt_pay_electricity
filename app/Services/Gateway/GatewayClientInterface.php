<?php

namespace App\Services\Gateway;

use App\Models\Payment;
use App\Models\Transaction;

interface GatewayClientInterface
{
    /**
     * Starts a payment with the gateway.
     *
     * @return array{gateway_reference: string, payload: array<string, mixed>}
     */
    public function initiate(Payment $payment, Transaction $transaction): array;
}

