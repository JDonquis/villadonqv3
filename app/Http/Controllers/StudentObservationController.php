<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreObservationRequest;
use App\Http\Requests\UpdateObservationRequest;
use App\Models\EvaluationPlan;
use App\Models\StudentObservation;
use App\Services\StudentObservationService;
use App\Support\ErrorTranslator;
use App\Support\GradeAccess;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class StudentObservationController extends Controller
{
    private StudentObservationService $observationService;

    public function __construct()
    {
        $this->observationService = new StudentObservationService;
    }

    /**
     * Resuelve el plan pedido y aborta con 404 si el usuario no puede gestionarlo.
     * Mismo criterio que `StudentGradeController::authorizePlan()`: 404 y no 403 para
     * no revelar la existencia de planes de otros profesores.
     */
    private function authorizePlan(int $planId): EvaluationPlan
    {
        $plan = EvaluationPlan::with('matter')->findOrFail($planId);

        abort_unless(GradeAccess::canManagePlan(auth()->user(), $plan), 404);

        return $plan;
    }

    private function authorizeObservation(StudentObservation $observation): StudentObservation
    {
        $observation->loadMissing(['plan.matter', 'author', 'student']);

        abort_unless($this->observationService->canModify($observation, auth()->user()), 403);

        return $observation;
    }

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'plan_id' => ['required', 'integer', 'exists:evaluation_plans,id'],
            'student_id' => ['required', 'integer', 'exists:students,id'],
        ]);

        $plan = $this->authorizePlan((int) $validated['plan_id']);

        abort_unless(
            StudentObservationService::planContainsStudent($plan, (int) $validated['student_id']),
            422,
            'El estudiante no pertenece al curso ni a la sección del plan.'
        );

        return response()->json([
            'data' => $this->observationService->listForStudent(
                $plan,
                (int) $validated['student_id'],
                auth()->user()
            ),
        ]);
    }

    public function store(StoreObservationRequest $request): JsonResponse
    {
        try {
            $plan = $request->plan();

            $observation = $this->observationService->create(
                $plan,
                (int) $request->input('student_id'),
                $request->type(),
                (string) $request->input('body'),
                $request->isShared(),
                (int) auth()->id()
            );

            $data = $this->observationService->listForStudent(
                $plan,
                (int) $request->input('student_id'),
                auth()->user()
            );

            return response()->json([
                'data' => $data,
                'message' => 'Observación guardada correctamente.',
            ], 201);
        } catch (Exception $e) {
            Log::error('Error al guardar observación: '.$e->getMessage());

            return response()->json([
                'message' => ErrorTranslator::translate($e),
            ], 500);
        }
    }

    public function update(UpdateObservationRequest $request, StudentObservation $observation): JsonResponse
    {
        $this->authorizeObservation($observation);

        try {
            $updated = $this->observationService->update(
                $observation,
                $request->type(),
                (string) $request->input('body'),
                $request->isShared(),
                (int) auth()->id()
            );

            $data = $this->observationService->listForStudent(
                $updated->plan,
                (int) $updated->student_id,
                auth()->user()
            );

            return response()->json([
                'data' => $data,
                'message' => 'Observación actualizada correctamente.',
            ]);
        } catch (Exception $e) {
            Log::error('Error al actualizar observación: '.$e->getMessage());

            return response()->json([
                'message' => ErrorTranslator::translate($e),
            ], 500);
        }
    }

    public function destroy(StudentObservation $observation): JsonResponse
    {
        $this->authorizeObservation($observation);

        try {
            $plan = $observation->plan;
            $studentId = (int) $observation->student_id;

            $this->observationService->delete($observation);

            return response()->json([
                'data' => $this->observationService->listForStudent($plan, $studentId, auth()->user()),
                'message' => 'Observación eliminada correctamente.',
            ]);
        } catch (Exception $e) {
            Log::error('Error al eliminar observación: '.$e->getMessage());

            return response()->json([
                'message' => ErrorTranslator::translate($e),
            ], 500);
        }
    }
}
