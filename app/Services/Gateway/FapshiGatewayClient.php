<?php

namespace App\Services\Gateway;

use App\Models\Payment;
use App\Models\Transaction;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

class FapshiGatewayClient implements GatewayClientInterface
{
    public function initiate(Payment $payment, Transaction $transaction): array
    {
        $baseUrl = (string) config('services.fapshi.base_url', 'https://sandbox.fapshi.com');
        $apiUser = (string) config('services.fapshi.apiuser', '');
        $apiKey = (string) config('services.fapshi.apikey', '');

        if ($apiUser === '' || $apiKey === '') {
            throw ValidationException::withMessages([
                'gateway' => ['Fapshi credentials are not configured. Set FAPSHI_APIUSER and FAPSHI_APIKEY.'],
            ]);
        }

        $payload = $transaction->response_payload ?? [];
        $rawPhone = (string) ($payload['phone'] ?? '');
        $phone = preg_replace('/\D+/', '', $rawPhone) ?? $rawPhone;
        $medium = $payload['medium'] ?? null;
        $name = $payload['name'] ?? null;
        $email = $payload['email'] ?? null;
        $message = $payload['message'] ?? null;

        if ($phone === '') {
            throw ValidationException::withMessages([
                'phone' => ['phone is required for Fapshi direct pay.'],
            ]);
        }
        if (! preg_match('/^\d{9}$/', $phone)) {
            throw ValidationException::withMessages([
                'phone' => ['phone must be exactly 9 digits (e.g. 67XXXXXXX).'],
            ]);
        }

        $body = [
            'amount' => (int) round((float) $payment->amount),
            'phone' => $phone,
            'userId' => (string) $payment->user_id,
            'externalId' => (string) $payment->reference,
        ];

        if (is_string($medium) && $medium !== '') {
            $body['medium'] = $medium;
        }
        if (is_string($name) && $name !== '') {
            $body['name'] = $name;
        }
        if (is_string($email) && $email !== '') {
            $body['email'] = $email;
        }
        if (is_string($message) && $message !== '') {
            $body['message'] = $message;
        }

        $res = Http::baseUrl($baseUrl)
            ->acceptJson()
            ->asJson()
            ->withHeaders([
                'apiuser' => $apiUser,
                'apikey' => $apiKey,
            ])
            ->post('/direct-pay', $body);

        if (! $res->ok()) {
            $msg = (string) ($res->json('message') ?? 'Direct pay initiation failed.');
            throw ValidationException::withMessages([
                'gateway' => [$msg],
            ]);
        }

        $json = $res->json();
        $transId = (string) ($json['transId'] ?? '');
        if ($transId === '') {
            throw ValidationException::withMessages([
                'gateway' => ['Fapshi response missing transId.'],
            ]);
        }

        return [
            'gateway_reference' => $transId,
            'payload' => $json,
        ];
    }
}

