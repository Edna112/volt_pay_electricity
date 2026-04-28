<?php

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo App\Models\Provider::query()
    ->select(['id', 'name', 'slug'])
    ->get()
    ->toJson(JSON_PRETTY_PRINT);

