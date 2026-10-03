<?php

namespace App\Http\Controllers;

use App\Models\BalanceStudent;
use App\Models\MainConfig;
use App\Services\AccountStatementService;
use Illuminate\Http\Request;

class AccountStatementController extends Controller
{
    public function index(Request $request)
    {
        $service = new AccountStatementService;
        $result = $service->getAll($request->all());
        $config = MainConfig::select('name', 'day_of_monthly_payment', 'grace_period', 'ame_price', 'investment_plan_price')->first();

        return inertia('Dashboard/EstadosDeCuenta', [
            'data' => $result,
            'config' => $config,
        ]);
    }

    public function marcarRecordatorio(Request $request)
    {
        \Log::info('marcarRecordatorio called', ['input' => $request->all()]);

        $request->validate([
            'balance_student_id' => 'required|exists:balance_students,id',
        ]);

        $balance = BalanceStudent::findOrFail($request->balance_student_id);
        \Log::info('Found balance', ['balance_id' => $balance->id]);

        // Determinar mes actual (en español, minúscula)
        $mesActual = strtolower(now()->format('F'));
        \Log::info('Current month', ['mesActual' => $mesActual]);

        $column = $mesActual . '_reminded';

        if (! in_array($mesActual, BalanceStudent::MONTHS, true)) {
            \Log::warning('Invalid month', ['mesActual' => $mesActual]);
            return response()->json(['success' => false, 'message' => 'Mes inválido'], 422);
        }

        if ($balance->$column) {
            \Log::info('Already marked', ['column' => $column]);
            return response()->json(['success' => true, 'message' => 'Ya estaba marcado', 'month' => $mesActual]);
        }

        $balance->$column = true;
        $balance->save();
        \Log::info('Saved successfully', ['column' => $column, 'new_value' => $balance->$column]);

        return response()->json(['success' => true, 'month' => $mesActual]);
    }
}
