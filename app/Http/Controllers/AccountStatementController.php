<?php

namespace App\Http\Controllers;

use App\Models\BalanceStudent;
use App\Models\MainConfig;
use App\Services\AccountStatementService;
use App\Services\BalanceMaintenanceService;
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
            'annulments' => (new BalanceMaintenanceService)->history($request->input('school_lapse_id')),
        ]);
    }

    public function previewAnnulMonths(Request $request)
    {
        $data = $request->validate([
            'school_lapse_id' => 'nullable|exists:school_lapses,id',
            'up_to_month' => 'required|string',
            'include_inscription' => 'boolean',
            'course_id' => 'nullable|integer',
            'section_id' => 'nullable|integer',
            'student_id' => 'nullable|integer|exists:students,id',
        ]);

        $preview = (new BalanceMaintenanceService)->preview(
            $data['school_lapse_id'] ?? null,
            $data['up_to_month'],
            (bool) ($data['include_inscription'] ?? false),
            ['course_id' => $data['course_id'] ?? null, 'section_id' => $data['section_id'] ?? null, 'student_id' => $data['student_id'] ?? null],
        );

        return response()->json($preview);
    }

    public function annulMonths(Request $request)
    {
        $data = $request->validate([
            'school_lapse_id' => 'nullable|exists:school_lapses,id',
            'up_to_month' => 'required|string',
            'include_inscription' => 'boolean',
            'course_id' => 'nullable|integer',
            'section_id' => 'nullable|integer',
            'student_id' => 'nullable|integer|exists:students,id',
        ]);

        $result = (new BalanceMaintenanceService)->annul(
            $data['school_lapse_id'] ?? null,
            $data['up_to_month'],
            (bool) ($data['include_inscription'] ?? false),
            ['course_id' => $data['course_id'] ?? null, 'section_id' => $data['section_id'] ?? null, 'student_id' => $data['student_id'] ?? null],
        );

        return response()->json(['success' => true] + $result);
    }

    public function undoAnnulment($id)
    {
        $result = (new BalanceMaintenanceService)->undo((int) $id);

        return response()->json(['success' => true] + $result);
    }

    public function marcarRecordatorio(Request $request)
    {
        $request->validate([
            'balance_student_id' => 'required|exists:balance_students,id',
        ]);

        $balance = BalanceStudent::findOrFail($request->balance_student_id);

        // Determinar mes actual (en inglés, minúscula) — las columnas usan ese formato.
        $mesActual = strtolower(now()->format('F'));

        $column = $mesActual.'_reminded';

        if (! in_array($mesActual, BalanceStudent::MONTHS, true)) {
            return response()->json(['success' => false, 'message' => 'Mes inválido'], 422);
        }

        if ($balance->$column) {
            return response()->json(['success' => true, 'message' => 'Ya estaba marcado', 'month' => $mesActual]);
        }

        $balance->$column = true;
        $balance->save();

        return response()->json(['success' => true, 'month' => $mesActual]);
    }
}
