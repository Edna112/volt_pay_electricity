<?php

namespace App\Services\Eneo;

use App\Models\Provider;

class MockEneoClient implements EneoClientInterface
{
    public function verifyMeterAndGetBill(string $meterNumber, Provider $provider): ?array
    {
        $number = trim($meterNumber);

        if ($number === '' || strcasecmp($number, 'invalid') === 0 || $number === '00000000') {
            return null;
        }

        return [
            'customer_name' => 'Mock Customer '.$number,
            'bill' => [
                'amount' => 15_000,
                'currency' => 'XAF',
                'reference' => 'ENEOMOCK-'.substr(md5($number.$provider->id), 0, 10),
                'due_date' => now()->addDays(14)->toDateString(),
                'period' => now()->format('F Y'),
            ],
        ];
    }
}
