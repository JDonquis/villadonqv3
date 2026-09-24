<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();
$u = \App\Models\User::where('email', 'sales43581@bullbaby.com')->first();
echo $u ? 'Found: ' . $u->name . ' (id: ' . $u->id . ', type: ' . $u->type_user_id . ')' : 'Not found';