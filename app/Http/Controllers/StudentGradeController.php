<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreGradesRequest;
use App\Models\EvaluationPlan;
use App\Models\SchoolLapse;
use App\Services\AttendanceService;
use App\Services\EvaluationPlanService;
use App\Services\StudentGradeService;
use App\Support\ErrorTranslator;
use App\Support\GradeAccess;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class StudentGradeController extends Controller
{
    private StudentGradeService $gradeService;
    private EvaluationPlanService $planService;
    private AttendanceService $attendanceService;

    public function __construct()
    {
        $this->gradeService = new StudentGradeService;
        $this->planService = new EvaluationPlanService;
        $this->attendanceService = new AttendanceService;
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

        $filters = [
            'school_lapse_id' => $schoolLapseId,
            'lapse_id' => $lapseId,
            'status' => 'approved',
        ];

        // La administración ve todos los planes aprobados; el profesor solo los suyos.
        $managesAll = GradeAccess::managesAllPlans(auth()->user());

        $plans = $managesAll
            ? $this->planService->getPlansForAdmin($filters)
            : $this->planService->getPlansForTeacher((int) auth()->id(), $filters);

        $selectedPlanId = (int) ($request->input('plan_id') ?: ($plans[0]['id'] ?? null));

        $matrix = $selectedPlanId && GradeAccess::canManagePlan(auth()->user(), EvaluationPlan::find($selectedPlanId))
            ? $this->gradeService->getMatrixData($selectedPlanId)
            : null;

        return inertia('Dashboard/MisEstudiantes', [
            'data' => [
                'plans' => $plans,
                'matrix' => $matrix,
                'selected_plan_id' => $selectedPlanId,
                'school_lapse_id' => $schoolLapseId,
                'lapse_id' => $lapseId,
                'school_lapses' => $this->planService->getSchoolLapses(),
                'manages_all' => $managesAll,
            ],
        ]);
    }

    /**
     * Resuelve el plan solicitado y aborta con 404 si el usuario no puede calificarlo.
     * Se usa 404 (y no 403) para no revelar la existencia de planes de otros profesores.
     */
    private function authorizePlan(int $planId): EvaluationPlan
    {
        $plan = EvaluationPlan::with('teacher')->findOrFail($planId);

        abort_unless(GradeAccess::canManagePlan(auth()->user(), $plan), 404);

        return $plan;
    }

    public function attendanceMatrix(int $planId)
    {
        $plan = $this->authorizePlan($planId);

        $data = $this->attendanceService->getAttendanceMatrix($plan->id);

        return response()->json(['data' => $data]);
    }

    public function createAttendanceSession(Request $request)
    {
        $request->validate([
            'plan_id' => ['required', 'integer', 'exists:evaluation_plans,id'],
            'date' => ['required', 'date'],
        ]);

        $plan = $this->authorizePlan((int) $request->input('plan_id'));

        try {
            $session = $this->attendanceService->getOrCreateSession(
                $plan->id,
                $request->input('date')
            );

            // Return session data in the same format as getAttendanceMatrix
            $sessionData = [
                'id' => $session->id,
                'date' => $session->date->toDateString(),
                'order' => $session->order,
                'day_of_week' => ucfirst(str_replace('.', '', $session->date->locale('es')->isoFormat('ddd'))),
            ];

            return response()->json([
                'data' => $sessionData,
                'message' => 'Sesión creada correctamente.',
            ]);
        } catch (Exception $e) {
            Log::error('Error al crear sesión de asistencia: ' . $e->getMessage());
            return back()->withErrors(['message' => ErrorTranslator::translate($e)]);
        }
    }

    public function deleteAttendanceSession(int $sessionId)
    {
        $session = \App\Models\EvaluationPlanAttendanceSession::with('plan')
            ->findOrFail($sessionId);

        abort_unless(GradeAccess::canManagePlan(auth()->user(), $session->plan), 403);

        try {
            $this->attendanceService->deleteSession($sessionId);

            return response()->json(['message' => 'Sesión eliminada correctamente.']);
        } catch (Exception $e) {
            Log::error('Error al eliminar sesión de asistencia: ' . $e->getMessage());
            return response()->json(['message' => ErrorTranslator::translate($e)], 500);
        }
    }

    public function saveAttendance(Request $request)
    {
        $request->validate([
            'plan_id' => ['required', 'integer', 'exists:evaluation_plans,id'],
            'records' => ['required', 'array'],
            'records.*.session_id' => ['required', 'integer'],
            'records.*.student_id' => ['required', 'integer'],
            'records.*.status' => ['required', 'string', 'in:absent,present,excused'],
        ]);

        $plan = $this->authorizePlan((int) $request->input('plan_id'));

        try {
            $saved = $this->attendanceService->saveAttendance(
                $plan->id,
                $request->input('records')
            );

            return back()->with([
                'status' => true,
                'message' => "Asistencia guardada correctamente ({$saved} registros).",
            ]);
        } catch (Exception $e) {
            Log::error('Error al guardar asistencia: ' . $e->getMessage());
            return back()->withErrors(['message' => ErrorTranslator::translate($e)]);
        }
    }

    public function saveGrades(StoreGradesRequest $request)
    {
        $plan = EvaluationPlan::findOrFail($request->input('plan_id'));

        if (! GradeAccess::canManagePlan(auth()->user(), $plan)) {
            return back()->withErrors(['message' => 'No tienes permisos para calificar este plan.']);
        }

        try {
            $this->gradeService->saveGrades(
                $plan->id,
                $request->input('grades', []),
                $request->input('rasgos', []),
                (int) auth()->id()
            );

            return back()->with(['status' => true, 'message' => 'Notas guardadas correctamente.']);
        } catch (Exception $e) {
            Log::error('Error al guardar notas: '.$e->getMessage());

            return back()->withErrors(['message' => ErrorTranslator::translate($e)]);
        }
    }

    public function publishGrades(Request $request)
    {
        $request->validate(['plan_id' => ['required', 'integer', 'exists:evaluation_plans,id']]);

        $plan = EvaluationPlan::findOrFail($request->input('plan_id'));

        if (! GradeAccess::canManagePlan(auth()->user(), $plan)) {
            return back()->withErrors(['message' => 'No tienes permisos para publicar este plan.']);
        }

        try {
            $this->gradeService->publishGrades($plan->id, (int) auth()->id());

            return back()->with(['status' => true, 'message' => 'Notas publicadas correctamente.']);
        } catch (Exception $e) {
            Log::error('Error al publicar notas: '.$e->getMessage());

            return back()->withErrors(['message' => ErrorTranslator::translate($e)]);
        }
    }
}
