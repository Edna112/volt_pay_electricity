<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use App\Services\SettlementService;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('settle:eneo {--limit=200 : Max payments to settle}', function (SettlementService $settlements) {
    $limit = (int) $this->option('limit');
    $out = $settlements->settlePendingToEneo($limit);

    $this->info('Settlement complete.');
    $this->line('Settlement ref: ' . $out['settlement_reference']);
    $this->line('Count: ' . $out['count']);
    $this->line('Total net (XAF): ' . $out['total_net']);
})->purpose('Settle paid payments to ENEO (mock)');
