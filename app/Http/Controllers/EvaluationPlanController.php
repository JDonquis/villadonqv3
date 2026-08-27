<?php

namespace App\Http\Controllers;

use App\Enums\EvaluationPlanStatusEnum;
use App\Enums\UserTypeEnum;
use App\Http\Requests\RejectEvaluationPlanRequest;
use App\Http\Requests\StoreEvaluationPlanRequest;
use App\Http\Requests\UpdateEvaluationPlanRequest;
use App\Models\EvaluationPlan;
use App\Models\User;
use App\Services\EvaluationPlanService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class EvaluationPlanController extends Controller
{
    private EvaluationPlanService $planService;

    public function __construct()
    {
        $this->planService = new EvaluationPlanService;
    }

    public function index(Request $request)
    {
        $teachers = User::where('type_user_id', UserTypeEnum::Teacher->value)
            ->orderBy('name')
            ->get()
            ->map(fn ($t) => ['id' => $t->id, 'name' => $t->name.' '.$t->last_name])
            ->values();

        return inertia('Dashboard/PlanesEvaluacion', [
            'data' => [
                'plans' => $this->planService->getPlansForAdmin($request->all()),
                'matters' => $this->planService->getMatters(),
                'teachers' => $teachers,
                'statuses' => collect(EvaluationPlanStatusEnum::cases())->map(fn ($s) => [
                    'value' => $s->value,
                    'label' => $s->label(),
                ])->values(),
            ],
            'filters' => [
                'status' => $request->input('status') ?? null,
                'matter_id' => $request->input('matter_id') ?? null,
                'teacher_id' => $request->input('teacher_id') ?? null,
            ],
        ]);
    }

    public function myPlans()
    {
        $teacherMatters = auth()->user()->matters()
            ->orderBy('name')
            ->get()
            ->map(fn ($m) => ['id' => $m->id, 'name' => $m->name])
            ->values();

        return inertia('Dashboard/MisPlanes', [
            'data' => [
                'plans' => $this->planService->getPlansForTeacher(auth()->id()),
                'matters' => $teacherMatters,
                'courses' => $this->planService->getCourses(),
                'sections' => $this->planService->getSections(),
                'school_lapses' => $this->planService->getSchoolLapses(),
            ],
        ]);
    }

    public function store(StoreEvaluationPlanRequest $request)
    {
        try {
            $this->planService->createPlan(auth()->id(), $request->validated());

            return back()->with(['status' => true, 'message' => 'Plan de evaluación creado correctamente.']);
        } catch (Exception $e) {
            Log::error('Error al crear plan de evaluación: '.$e->getMessage());

            return back()->withErrors(['message' => $e->getMessage()]);
        }
    }

    public function update(UpdateEvaluationPlanRequest $request, $id)
    {
        $plan = EvaluationPlan::findOrFail($id);

        if ($plan->user_id !== auth()->id() || ! $this->canEdit($plan)) {
            return back()->withErrors(['message' => 'No tienes permisos para editar este plan.']);
        }

        try {
            $this->planService->updatePlan($plan, $request->validated());

            return back()->with(['status' => true, 'message' => 'Plan de evaluación actualizado correctamente.']);
        } catch (Exception $e) {
            Log::error('Error al actualizar plan de evaluación ID '.$id.': '.$e->getMessage());

            return back()->withErrors(['message' => $e->getMessage()]);
        }
    }

    public function destroy($id)
    {
        $plan = EvaluationPlan::findOrFail($id);

        if ($plan->user_id !== auth()->id() || ! $this->canEdit($plan)) {
            return back()->withErrors(['message' => 'No tienes permisos para eliminar este plan.']);
        }

        try {
            $this->planService->deletePlan($plan);

            return back()->with(['status' => true, 'message' => 'Plan de evaluación eliminado correctamente.']);
        } catch (Exception $e) {
            Log::error('Error al eliminar plan de evaluación ID '.$id.': '.$e->getMessage());

            return back()->withErrors(['message' => $e->getMessage()]);
        }
    }

    public function approve($id)
    {
        $plan = EvaluationPlan::findOrFail($id);

        try {
            $this->planService->approve($plan, auth()->id());

            return back()->with(['status' => true, 'message' => 'Plan aprobado correctamente.']);
        } catch (Exception $e) {
            Log::error('Error al aprobar plan ID '.$id.': '.$e->getMessage());

            return back()->withErrors(['message' => $e->getMessage()]);
        }
    }

    public function reject(RejectEvaluationPlanRequest $request, $id)
    {
        $plan = EvaluationPlan::findOrFail($id);

        try {
            $this->planService->reject($plan, auth()->id(), $request->input('admin_note'));

            return back()->with(['status' => true, 'message' => 'Plan rechazado correctamente.']);
        } catch (Exception $e) {
            Log::error('Error al rechazar plan ID '.$id.': '.$e->getMessage());

            return back()->withErrors(['message' => $e->getMessage()]);
        }
    }

    private function canEdit(EvaluationPlan $plan): bool
    {
        return in_array($plan->status, [
            EvaluationPlanStatusEnum::Pending->value,
            EvaluationPlanStatusEnum::Rejected->value,
        ], true);
    }
}
