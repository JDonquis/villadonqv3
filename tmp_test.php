<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$r = app(App\Services\AccountStatementService::class)->getAll(['per_page'=>500, 'search'=>'Carlos Eduardo']);
foreach ($r['students'] as $s) {
    foreach (collect($s['balances']) as $b) {
        $bps = $b['balance_payments'];
        foreach ($bps as $k => $list) {
            foreach (collect($list) as $bp) {
                if (isset($bp['amount'])) {
                    echo $s['name'].' '.$s['last_name'].' | '.$k.' | $'.$bp['amount'].' | '.$bp['concept']."\n";
                }
            }
        }
    }
}