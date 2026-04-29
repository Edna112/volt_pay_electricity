<?php

namespace App\Services;

use App\Models\Payment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SettlementService
{
    /**
     * Settle paid payments to ENEO (mock).
     *
     * @return array{count:int,total_net:float,settlement_reference:string}
     */
    public function settlePendingToEneo(int $limit = 200): array
    {
        $settlementReference = 'ENEO-SET-' . Str::upper(Str::random(12));

        $result = DB::transaction(function () use ($limit, $settlementReference) {
            $payments = Payment::query()
                ->where('status', 'paid')
                ->where('settlement_status', 'pending')
                ->where('net_amount', '>', 0)
                ->lockForUpdate()
                ->limit($limit)
                ->get();

            $totalNet = (float) $payments->sum('net_amount');

            if ($payments->isEmpty()) {
                return [
                    'count' => 0,
                    'total_net' => 0.0,
                    'settlement_reference' => $settlementReference,
                ];
            }

            // Mock "send to ENEO" — in production this would call ENEO settlement API.
            Log::info('Settling payments to ENEO (mock)', [
                'settlement_reference' => $settlementReference,
                'count' => $payments->count(),
                'total_net' => $totalNet,
                'payment_ids' => $payments->pluck('id')->all(),
            ]);

            Payment::query()
                ->whereIn('id', $payments->pluck('id'))
                ->update([
                    'settlement_status' => 'settled',
                    'settled_at' => now(),
                    'settlement_reference' => $settlementReference,
                    'updated_at' => now(),
                ]);

            return [
                'count' => $payments->count(),
                'total_net' => $totalNet,
                'settlement_reference' => $settlementReference,
            ];
        });

        return $result;
    }
}

