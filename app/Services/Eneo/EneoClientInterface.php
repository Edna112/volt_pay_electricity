<?php

namespace App\Services\Eneo;

use App\Models\Provider;

interface EneoClientInterface
{
    /**
     * Call the real ENEO API in production. Mock returns sample data.
     *
     * @return array{customer_name: string, bill: array<string, mixed>}|null Null when the meter is unknown or invalid.
     */
    public function verifyMeterAndGetBill(string $meterNumber, Provider $provider): ?array;
}
