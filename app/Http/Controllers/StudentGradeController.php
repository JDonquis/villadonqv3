<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreGradesRequest;
use App\Models\EvaluationPlan;
use App\Models\SchoolLapse;
use App\Services\EvaluationPlanService;
use App\Services\StudentGradeService;
use App\Support\ErrorTranslator;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class StudentGradeController extends Controller
{
    private StudentGradeService $gradeService;

    private EvaluationPlanService $planService;

    public function __construct()
    {
        $this->gradeService = new StudentGradeService;
        $this->planService = new EvaluationPlanService;
    }

    public function index(Request $request)
    {
        $schoolLapseId = (int) ($request->input('school_lapse_id') ?: $this->planService->currentSchoolLapseId());

        $schoolLapse = SchoolLapse::with('lapses')->find($schoolLapseId);
        $defaultLapseId = $request->input('lapse_id');

        if (empty($defaultLapseId) && $schoolLapse) {
            $today = Carbon::now()->toDateString();
            $defaultLapseId = $schoolLapse->lapses
                ->first(fn ($lap) => $today >= ($lap->start ?? '') && $today <= ($lap->end ?? ''))
                ?->id
                ?? $schoolLapse->lapses->sortByDesc('number')->first()?->id;
        }

        $lapseId = $defaultLapseId ? (int) $defaultLapseId : null;

        $plans = $this->planService->getPlansForTeacher(auth()->id(), [
            'school_lapse_id' => $schoolLapseId,
            'lapse_id' => $lapseId,
            'status' => 'approved',
        ]);

        $selectedPlanId = (int) ($request->input('plan_id') ?: ($plans[0]['id'] ?? null));

        $matrix = $selectedPlanId ? $this->gradeService->getMatrixData($selectedPlanId) : null;

        return inertia('Dashboard/MisEstudiantes', [
            'data' => [
                'plans' => $plans,
                'matrix' => $matrix,
                'selected_plan_id' => $selectedPlanId,
                'school_lapse_id' => $schoolLapseId,
                'lapse_id' => $lapseId,
                'school_lapses' => $this->planService->getSchoolLapses(),
            ],
        ]);
    }

    public function saveGrades(StoreGradesRequest $request)
    {
        $plan = EvaluationPlan::findOrFail($request->input('plan_id'));

        if ($plan->user_id !== auth()->id()) {
            return back()->withErrors(['message' => 'No tienes permisos para calificar este plan.']);
        }

        try {
            $this->gradeService->saveGrades($plan->id, $request->input('grades', []), $request->input('rasgos', []));

            return back()->with(['status' => true, 'message' => 'Notas guardadas correctamente.']);
        } catch (Exception $e) {
            Log::error('Error al guardar notas: '.$e->getMessage());

            return back()->withErrors(['message' => ErrorTranslator::translate($e)]);
        }
    }

    public function publishGrades(Request $request)
    {
        $request->validate(['plan_id' => ['required', 'integer', 'exists:evaluation_plans,id']]);

        try {
            $this->gradeService->publishGrades((int) $request->input('plan_id'), (int) auth()->id());

            return back()->with(['status' => true, 'message' => 'Notas publicadas correctamente.']);
        } catch (Exception $e) {
            Log::error('Error al publicar notas: '.$e->getMessage());

            return back()->withErrors(['message' => ErrorTranslator::translate($e)]);
        }
    }
}
