<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\VerifyMeterRequest;
use App\Services\MeterService;
use Illuminate\Http\JsonResponse;

class MeterController extends Controller
{
    public function verify(VerifyMeterRequest $request, MeterService $meters): JsonResponse
    {
        $data = $meters->verifyAndStore(
            $request->user(),
            (int) $request->validated('provider_id'),
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
