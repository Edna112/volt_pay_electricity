<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\EneoVerifyMeterRequest;
use App\Models\Provider;
use App\Services\MeterService;
use Illuminate\Http\JsonResponse;

class EneoController extends Controller
{
    public function verifyMeter(EneoVerifyMeterRequest $request, MeterService $meters): JsonResponse
    {
        // Ensure ENEO exists as a provider record.
        $provider = Provider::query()->firstOrCreate(
            ['slug' => 'eneo'],
            ['name' => 'ENEO', 'slug' => 'eneo']
        );

        $data = $meters->verifyAndStore(
            $request->user(),
            (int) $provider->getKey(),
            $request->validated('meter_number'),
            $request->validated('alias')
        );

        return response()->json([
            'message' => 'Meter verified and saved.',
            'meter' => $data['meter'],
            'customer_name' => $data['customer_name'],
            'bill' => $data['bill'],
        ]);
    }
}

