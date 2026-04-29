<?php

namespace App\Services;

use App\Models\Meter;
use App\Models\Payment;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Gateway\GatewayClientInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PaymentService
{
    public function __construct(
        private readonly GatewayClientInterface $gateway
    ) {}

    private function platformFee(): float
    {
        return (float) config('voltpay.platform_fee_xaf', 200);
    }

    /**
     * Create Payment + Transaction (pending) and start gateway flow.
     * Idempotent by (user_id + idempotency_key).
     *
     * @return array{payment: Payment, transaction: Transaction, gateway: array<string, mixed>}
     */
    public function createAndInitiate(
        User $user,
        int $meterId,
        float $amount,
        string $reference,
        string $gateway,
        string $idempotencyKey
    ): array {
        return $this->createAndInitiateWithGatewayClient(
            $user,
            $meterId,
            $amount,
            $reference,
            $gateway,
            $idempotencyKey,
            $this->gateway,
            null
        );
    }

    /**
     * Same as createAndInitiate, but allows specifying the gateway client and optional gateway meta.
     *
     * @param  array<string, mixed>|null  $gatewayMeta
     * @return array{payment: Payment, transaction: Transaction, gateway: array<string, mixed>}
     */
    public function createAndInitiateWithGatewayClient(
        User $user,
        int $meterId,
        float $amount,
        string $reference,
        string $gateway,
        string $idempotencyKey,
        GatewayClientInterface $client,
        ?array $gatewayMeta
    ): array {
        return DB::transaction(function () use ($user, $meterId, $amount, $reference, $gateway, $idempotencyKey, $client, $gatewayMeta) {
            $fee = $this->platformFee();
            if ($amount < $fee) {
                throw ValidationException::withMessages([
                    'amount' => ["Amount must be at least {$fee} XAF to cover the platform fee."],
                ]);
            }

            $netAmount = max(0, $amount - $fee);

            // Authorization: user can only pay for their own meter.
            $meter = Meter::query()
                ->whereKey($meterId)
                ->where('user_id', $user->getKey())
                ->first();

            if (! $meter) {
                Log::warning('Payment attempt for unauthorized meter', [
                    'user_id' => (string) $user->getKey(),
                    'meter_id' => $meterId,
                ]);

                throw ValidationException::withMessages([
                    'meter_id' => ['You are not allowed to pay for this meter.'],
                ]);
            }

            // If the same request is retried, return the already-created payment.
            $existing = Payment::query()
                ->where('user_id', $user->getKey())
                ->where('idempotency_key', $idempotencyKey)
                ->with('transactions')
                ->first();

            if ($existing) {
                $tx = $existing->transactions()->latest('id')->first();

                Log::info('Idempotent payment request replayed', [
                    'payment_id' => (int) $existing->getKey(),
                    'transaction_id' => $tx?->getKey(),
                    'user_id' => (string) $user->getKey(),
                ]);

                return [
                    'payment' => $existing,
                    'transaction' => $tx ?? new Transaction(),
                    'gateway' => ['status' => 'idempotent_replay'],
                ];
            }

            // Also treat (user_id + meter_id + reference) as idempotent, since it is unique in DB.
            $existingByReference = Payment::query()
                ->where('user_id', $user->getKey())
                ->where('meter_id', (int) $meter->getKey())
                ->where('reference', $reference)
                ->with('transactions')
                ->first();

            if ($existingByReference) {
                $tx = $existingByReference->transactions()->latest('id')->first();

                Log::info('Payment reference replayed; returning existing payment', [
                    'payment_id' => (int) $existingByReference->getKey(),
                    'transaction_id' => $tx?->getKey(),
                    'user_id' => (string) $user->getKey(),
                    'reference' => $reference,
                ]);

                return [
                    'payment' => $existingByReference,
                    'transaction' => $tx ?? new Transaction(),
                    'gateway' => ['status' => 'reference_replay'],
                ];
            }

            $payment = Payment::query()->create([
                'user_id' => $user->getKey(),
                'meter_id' => (int) $meter->getKey(),
                'amount' => $amount,
                'platform_fee' => $fee,
                'net_amount' => $netAmount,
                'reference' => $reference,
                'status' => 'pending',
                'settlement_status' => 'pending',
                'idempotency_key' => $idempotencyKey,
            ]);

            $transaction = Transaction::query()->create([
                'payment_id' => $payment->getKey(),
                'gateway' => $gateway,
                'gateway_reference' => null,
                'amount' => $amount,
                'status' => 'pending',
                'response_payload' => $gatewayMeta,
                'processed_at' => null,
            ]);

            Log::info('Payment and transaction created (pending)', [
                'payment_id' => (int) $payment->getKey(),
                'transaction_id' => (int) $transaction->getKey(),
                'user_id' => (string) $user->getKey(),
                'meter_id' => $meterId,
                'amount' => $amount,
                'platform_fee' => $fee,
                'net_amount' => $netAmount,
                'reference' => $reference,
                'gateway' => $gateway,
            ]);

            // Call gateway outside of any other queries but still inside transaction for consistency of saves.
            $gw = $client->initiate($payment, $transaction);

            $transaction->gateway_reference = $gw['gateway_reference'];
            $transaction->response_payload = $gw['payload'];
            $transaction->save();

            Log::info('Gateway initiation completed', [
                'payment_id' => (int) $payment->getKey(),
                'transaction_id' => (int) $transaction->getKey(),
                'gateway' => $gateway,
                'gateway_reference' => (string) $transaction->gateway_reference,
            ]);

            return [
                'payment' => $payment->fresh('transactions'),
                'transaction' => $transaction,
                'gateway' => $gw['payload'],
            ];
        });
    }

    /**
     * Process webhook callback (idempotent).
     *
     * @param  array<string, mixed>  $payload
     */
    public function handleWebhook(string $gateway, string $gatewayReference, string $status, array $payload = []): void
    {
        DB::transaction(function () use ($gateway, $gatewayReference, $status, $payload) {
            /** @var Transaction|null $tx */
            $tx = Transaction::query()
                ->where('gateway', $gateway)
                ->where('gateway_reference', $gatewayReference)
                ->lockForUpdate()
                ->first();

            if (! $tx) {
                Log::warning('Webhook for unknown gateway reference', [
                    'gateway' => $gateway,
                    'gateway_reference' => $gatewayReference,
                ]);

                return;
            }

            if ($tx->processed_at) {
                Log::info('Duplicate webhook ignored (already processed)', [
                    'transaction_id' => (int) $tx->getKey(),
                    'gateway' => $gateway,
                    'gateway_reference' => $gatewayReference,
                ]);

                return;
            }

            $normalized = Str::lower($status);
            $finalTxStatus = in_array($normalized, ['success', 'successful', 'paid', 'completed'], true)
                ? 'success'
                : (in_array($normalized, ['failed', 'error', 'expired', 'cancelled', 'canceled'], true) ? 'failed' : 'pending');

            $tx->status = $finalTxStatus;
            $tx->response_payload = $payload;
            $tx->processed_at = now();
            $tx->save();

            $payment = $tx->payment()->lockForUpdate()->first();

            if ($payment) {
                $payment->status = $finalTxStatus === 'success' ? 'paid' : ($finalTxStatus === 'failed' ? 'failed' : 'pending');
                if ($finalTxStatus === 'success') {
                    // Fee/net amounts are stored at initiation time; ensure settlement is pending.
                    $payment->settlement_status = $payment->net_amount > 0 ? 'pending' : 'skipped';
                }
                $payment->save();
            }

            Log::info('Webhook processed; statuses updated', [
                'payment_id' => $payment?->getKey(),
                'transaction_id' => (int) $tx->getKey(),
                'gateway' => $gateway,
                'gateway_reference' => $gatewayReference,
                'transaction_status' => $tx->status,
                'payment_status' => $payment?->status,
            ]);
        });
    }
}

