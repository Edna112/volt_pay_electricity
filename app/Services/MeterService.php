<?php

namespace App\Services;

use App\Models\Meter;
use App\Models\Provider;
use App\Models\User;
use App\Services\Eneo\EneoClientInterface;
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

        $remote = $this->eneo->verifyMeterAndGetBill($meterNumber, $provider);

        if ($remote === null) {
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

        return [
            'meter' => $meter,
            'bill' => $remote['bill'],
            'customer_name' => $remote['customer_name'],
        ];
    }
}
