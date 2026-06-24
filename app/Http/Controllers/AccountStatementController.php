<?php

namespace App\Http\Controllers;

use App\Models\MainConfig;
use App\Services\AccountStatementService;
use Illuminate\Http\Request;

class AccountStatementController extends Controller
{
    public function index(Request $request)
    {
        $service = new AccountStatementService;
        $result = $service->getAll($request->all());
        $config = MainConfig::select('day_of_monthly_payment', 'grace_period')->first();

        return inertia('Dashboard/EstadosDeCuenta', [
            'data' => $result,
            'config' => $config,
        ]);
    }
}
