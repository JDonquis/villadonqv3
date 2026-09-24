<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();
$u = \App\Models\User::where('is_admin', 1)->first();
echo $u ? 'Admin: ' . $u->email : 'No admin found';