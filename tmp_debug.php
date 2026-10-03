<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$bp = \App\Models\BalancePayment::where('amount', 10)->where('month', 'november')->first();
echo "bp#{$bp->id} payment_id={$bp->payment_id} balance_student_id={$bp->balance_student_id}\n";
if ($bp->payment) {
    echo "payment date={$bp->payment->date} raw={$bp->payment->raw_date}\n";
}