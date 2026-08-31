<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreGradesRequest;
use App\Models\EvaluationPlan;
use App\Services\EvaluationPlanService;
use App\Services\StudentGradeService;
use App\Support\ErrorTranslator;
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
        $plans = $this->planService->getPlansForTeacher(auth()->id());

        $selectedPlanId = (int) $request->input('plan_id') ?: ($plans[0]['id'] ?? null);

        $matrix = $selectedPlanId ? $this->gradeService->getMatrixData($selectedPlanId) : null;

        return inertia('Dashboard/MisEstudiantes', [
            'data' => [
                'plans' => $plans,
                'matrix' => $matrix,
                'selected_plan_id' => $selectedPlanId,
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
            $this->gradeService->saveGrades($plan->id, $request->input('grades', []));

            return back()->with(['status' => true, 'message' => 'Notas guardadas correctamente.']);
        } catch (Exception $e) {
            Log::error('Error al guardar notas: '.$e->getMessage());

            return back()->withErrors(['message' => ErrorTranslator::translate($e)]);
        }
    }
}
