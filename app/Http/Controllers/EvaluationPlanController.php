<?php

namespace App\Http\Controllers;

use App\Enums\EvaluationPlanStatusEnum;
use App\Enums\UserTypeEnum;
use App\Http\Requests\RejectEvaluationPlanRequest;
use App\Http\Requests\StoreAdminEvaluationPlanRequest;
use App\Http\Requests\StoreEvaluationPlanRequest;
use App\Http\Requests\UpdateEvaluationPlanRequest;
use App\Models\EvaluationPlan;
use App\Models\User;
use App\Services\EvaluationPlanService;
use App\Support\ErrorTranslator;
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
        $filters = $request->all();

        if (empty($filters['status'])) {
            $filters['status'] = EvaluationPlan::where('status', EvaluationPlanStatusEnum::Pending->value)->exists()
                ? EvaluationPlanStatusEnum::Pending->value
                : EvaluationPlanStatusEnum::Approved->value;
        }

        $teachers = User::where('type_user_id', UserTypeEnum::Teacher->value)
            ->with('matters:id')
            ->orderBy('name')
            ->get()
            ->map(fn ($t) => [
                'id' => $t->id,
                'name' => $t->name.' '.$t->last_name,
                'matter_ids' => $t->matters->pluck('id')->values(),
            ])
            ->values();

        return inertia('Dashboard/PlanesEvaluacion', [
            'data' => [
                'plans' => $this->planService->getPlansForAdmin($filters),
                'school_lapses' => $this->planService->getSchoolLapses(),
                'courses' => $this->planService->getCourses(),
                'sections' => $this->planService->getSections(),
                'matters' => $this->planService->getMatters(),
                'teachers' => $teachers,
                'statuses' => collect(EvaluationPlanStatusEnum::cases())
                    ->filter(fn ($s) => $s !== EvaluationPlanStatusEnum::Draft)
                    ->map(fn ($s) => [
                        'value' => $s->value,
                        'label' => $s->label(),
                    ])
                    ->values(),
            ],
            'filters' => [
                'status' => $filters['status'] ?? null,
                'search' => $filters['search'] ?? null,
                'school_lapse_id' => $filters['school_lapse_id'] ?? null,
                'lapse_id' => $filters['lapse_id'] ?? null,
                'course_id' => $filters['course_id'] ?? null,
                'section_id' => $filters['section_id'] ?? null,
                'matter_id' => $filters['matter_id'] ?? null,
                'teacher_id' => $filters['teacher_id'] ?? null,
                'open_plan' => session()->pull('open_plan') ?? null,
            ],
        ]);
    }

    public function storeByAdmin(StoreAdminEvaluationPlanRequest $request)
    {
        try {
            $data = $request->validated();
            $data['status'] = EvaluationPlanStatusEnum::Approved->value;
            $data['approved_by'] = auth()->id();
            $data['approved_at'] = now();

            $this->planService->createPlan((int) $data['teacher_id'], $data);

            return back()->with(['status' => true, 'message' => 'Plan de evaluación creado y aprobado correctamente.']);
        } catch (Exception $e) {
            Log::error('Error al crear plan de evaluación desde administración: '.$e->getMessage());

            return back()->withErrors(['message' => ErrorTranslator::translate($e)]);
        }
    }

    public function myPlans(Request $request)
    {
        $teacherMatters = auth()->user()->matters()
            ->orderBy('name')
            ->get()
            ->map(fn ($m) => ['id' => $m->id, 'name' => $m->name])
            ->values();

        return inertia('Dashboard/MisPlanes', [
            'data' => [
                'plans' => $this->planService->getPlansForTeacher(auth()->id(), $request->all()),
                'matters' => $teacherMatters,
                'courses' => $this->planService->getCourses(),
                'sections' => $this->planService->getSections(),
                'school_lapses' => $this->planService->getSchoolLapses(),
            ],
            'filters' => [
                'school_lapse_id' => $request->input('school_lapse_id'),
                'lapse_id' => $request->input('lapse_id'),
                'matter_id' => $request->input('matter_id'),
            ],
        ]);
    }

    public function allowedDays(Request $request)
    {
        $teacherId = auth()->user()->isTeacher()
            ? auth()->id()
            : $request->input('teacher_id');

        return response()->json(
            $this->planService->getAllowedWeekdays([
                'school_lapse_id' => $request->input('school_lapse_id'),
                'course_id' => $request->input('course_id'),
                'matter_id' => $request->input('matter_id'),
                'teacher_id' => $teacherId,
                'section_ids' => $request->input('section_ids', []),
            ])
        );
    }

    public function store(StoreEvaluationPlanRequest $request)
    {
        try {
            $this->planService->createPlan(auth()->id(), $request->validated());

            return back()->with(['status' => true, 'message' => 'Plan de evaluación creado correctamente.']);
        } catch (Exception $e) {
            Log::error('Error al crear plan de evaluación: '.$e->getMessage());

            return back()->withErrors(['message' => ErrorTranslator::translate($e)]);
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

            return back()->withErrors(['message' => ErrorTranslator::translate($e)]);
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

            return back()->withErrors(['message' => ErrorTranslator::translate($e)]);
        }
    }

    public function approve(Request $request, $id)
    {
        $plan = EvaluationPlan::findOrFail($id);

        try {
            $this->planService->approve($plan, auth()->id());

            return $this->redirectBackToPlans($request, 'Plan aprobado correctamente.');
        } catch (Exception $e) {
            Log::error('Error al aprobar plan ID '.$id.': '.$e->getMessage());

            return back()->withErrors(['message' => ErrorTranslator::translate($e)]);
        }
    }

    public function reject(RejectEvaluationPlanRequest $request, $id)
    {
        $plan = EvaluationPlan::findOrFail($id);

        try {
            $this->planService->reject($plan, auth()->id(), $request->input('admin_note'));

            return $this->redirectBackToPlans($request, 'Plan rechazado correctamente.');
        } catch (Exception $e) {
            Log::error('Error al rechazar plan ID '.$id.': '.$e->getMessage());

            return back()->withErrors(['message' => ErrorTranslator::translate($e)]);
        }
    }

    /**
     * Redirige de vuelta a la lista de planes conservando los filtros activos
     * y, en caso de aprobar/rechazar en modo "swipe", indica cuál es el próximo
     * plan pendiente para auto-abrirlo.
     */
    private function redirectBackToPlans(Request $request, string $message)
    {
        $filters = $request->only([
            'status', 'search', 'school_lapse_id', 'lapse_id',
            'course_id', 'section_id', 'matter_id', 'teacher_id',
        ]);
        $filters = array_filter($filters, fn ($v) => $v !== null && $v !== '');

        $url = '/dashboard/planes-evaluacion';
        if ($filters) {
            $url .= '?'.http_build_query($filters);
        }

        return redirect($url)
            ->with('status', true)
            ->with('message', $message)
            ->with('open_plan', $request->input('next_plan'));
    }

    private function canEdit(EvaluationPlan $plan): bool
    {
        return in_array($plan->status, [
            EvaluationPlanStatusEnum::Pending->value,
            EvaluationPlanStatusEnum::Rejected->value,
        ], true);
    }
}
