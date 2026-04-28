<?php

namespace App\Services;

use App\Models\Meter;
use App\Models\Provider;
use App\Models\User;
use App\Services\Eneo\EneoClientInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class MeterService
{
    public function __construct(
        private readonly EneoClientInterface $eneo
    ) {}

    /**
     * Verifies the meter with the provider (Eneo), stores the meter for the user, returns bill for payment.
     *
     * @return array{meter: Meter, bill: array<string, mixed>, customer_name: string}
     */
    public function verifyAndStore(User $user, int $providerId, string $meterNumber, ?string $alias = null): array
    {
        $provider = Provider::query()->findOrFail($providerId);

        $cacheKey = $this->billCacheKey((int) $provider->getKey(), $meterNumber);

        /** @var array{customer_name: string, bill: array<string, mixed>}|null $remote */
        $remote = Cache::get($cacheKey);

        if ($remote === null) {
            Log::info('Meter bill not found. Calling provider verification...', [
                'provider_id' => (int) $provider->getKey(),
                'meter_number' => $this->safeMeterForLog($meterNumber),
                'user_id' => (string) $user->getKey(),
            ]);

            $remote = $this->eneo->verifyMeterAndGetBill($meterNumber, $provider);

            if ($remote !== null) {
                Cache::put($cacheKey, $remote, now()->addMinutes(5));

                Log::info('Meter verified; bill cached', [
                    'provider_id' => (int) $provider->getKey(),
                    'meter_number' => $this->safeMeterForLog($meterNumber),
                    'user_id' => (string) $user->getKey(),
                    'cache_ttl_minutes' => 5,
                ]);
            }
        } else {
            Log::info('Meter bill cache hit', [
                'provider_id' => (int) $provider->getKey(),
                'meter_number' => $this->safeMeterForLog($meterNumber),
                'user_id' => (string) $user->getKey(),
            ]);
        }

        if ($remote === null) {
            Log::warning('Meter verification failed', [
                'provider_id' => (int) $provider->getKey(),
                'meter_number' => $this->safeMeterForLog($meterNumber),
                'user_id' => (string) $user->getKey(),
            ]);

            throw ValidationException::withMessages([
                'meter_number' => [__('The meter could not be verified with this provider.')],
            ]);
        }

        $meter = Meter::query()->updateOrCreate(
            [
                'user_id' => $user->getKey(),
                'provider_id' => $provider->getKey(),
                'meter_number' => $meterNumber,
            ],
            [
                'alias' => $alias,
            ],
        );

        $meter->load('provider');

        Log::info('Meter saved for user', [
            'meter_id' => (int) $meter->getKey(),
            'provider_id' => (int) $provider->getKey(),
            'meter_number' => $this->safeMeterForLog($meterNumber),
            'user_id' => (string) $user->getKey(),
        ]);

        return [
            'meter' => $meter,
            'bill' => $remote['bill'],
            'customer_name' => $remote['customer_name'],
        ];
    }

    private function billCacheKey(int $providerId, string $meterNumber): string
    {
        $normalized = preg_replace('/\s+/', '', trim($meterNumber)) ?? trim($meterNumber);

        return 'meters:bill:v1:provider='.$providerId.':meter='.mb_strtolower($normalized);
    }

    private function safeMeterForLog(string $meterNumber): string
    {
        $normalized = preg_replace('/\s+/', '', trim($meterNumber)) ?? trim($meterNumber);
        $normalized = mb_strtolower($normalized);

        if ($normalized === '') {
            return '';
        }

        // Avoid logging full meter numbers; keep last 4 chars.
        $last4 = mb_substr($normalized, -4);

        return '***'.$last4;
    }
}
