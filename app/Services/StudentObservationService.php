<?php

namespace App\Services;

use App\Enums\StudentObservationTypeEnum;
use App\Models\EvaluationPlan;
use App\Models\Student;
use App\Models\StudentObservation;
use App\Models\User;
use App\Support\GradeAccess;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class StudentObservationService
{
    public const MAX_BODY_LENGTH = 1000;

    /**
     * Pertenencia al plan: mismo criterio que usa la matriz de calificaciones
     * (`StudentGradeService::getMatrixData`): curso + sección + no graduado/inactivo.
     */
    public static function planContainsStudent(EvaluationPlan $plan, int $studentId): bool
    {
        return Student::where('id', $studentId)
            ->where('course_id', $plan->course_id)
            ->where('section_id', $plan->section_id)
            ->where('status', '!=', 0)
            ->exists();
    }

    /**
     * Historial de un estudiante dentro de un plan, con los contadores por tipo
     * que muestra el encabezado del drawer.
     */
    public function listForStudent(EvaluationPlan $plan, int $studentId, ?User $actor = null): array
    {
        $observations = StudentObservation::with(['author', 'plan.matter'])
            ->where('evaluation_plan_id', $plan->id)
            ->where('student_id', $studentId)
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        return [
            'observations' => $observations
                ->map(fn (StudentObservation $observation) => $this->formatObservation($observation, $actor))
                ->values()
                ->all(),
            'counts' => $this->counts($observations),
        ];
    }

    /**
     * @return array{positive: int, neutral: int, negative: int}
     */
    public function counts(Collection $observations): array
    {
        $counts = ['positive' => 0, 'neutral' => 0, 'negative' => 0];

        foreach ($observations as $observation) {
            $type = $observation->type instanceof StudentObservationTypeEnum
                ? $observation->type
                : StudentObservationTypeEnum::tryFrom((string) $observation->type);

            if ($type !== null) {
                $counts[$type->value]++;
            }
        }

        return $counts;
    }

    public function create(
        EvaluationPlan $plan,
        int $studentId,
        StudentObservationTypeEnum $type,
        string $body,
        bool $shared,
        int $actorId
    ): StudentObservation {
        return StudentObservation::create([
            'evaluation_plan_id' => $plan->id,
            'student_id' => $studentId,
            'created_by' => $actorId,
            'type' => $type->value,
            'body' => $this->normalizeBody($body),
            'shared_with_representative' => $shared,
            'shared_at' => $shared ? Carbon::now() : null,
        ]);
    }

    public function update(
        StudentObservation $observation,
        StudentObservationTypeEnum $type,
        string $body,
        bool $shared,
        int $actorId
    ): StudentObservation {
        $wasShared = (bool) $observation->shared_with_representative;

        $observation->type = $type->value;
        $observation->body = $this->normalizeBody($body);
        $observation->shared_with_representative = $shared;

        // `shared_at` marca desde cuándo está visible el representante: se fija al
        // compartir y se limpia al dejar de compartir, pero no se reinicia al editar.
        if ($shared && ! $wasShared) {
            $observation->shared_at = Carbon::now();
        } elseif (! $shared) {
            $observation->shared_at = null;
        }

        // Se conserva el autor original: la auditoría no se reescribe al editar.
        $observation->save();

        return $observation->refresh();
    }

    public function delete(StudentObservation $observation): void
    {
        $observation->delete();
    }

    /**
     * Totales de observaciones por estudiante para un plan, en una sola consulta.
     * Lo usa la matriz de calificaciones para el contador del botón de cada
     * estudiante (`StudentGradeService::getMatrixData`).
     *
     * No filtra por `shared_with_representative` a propósito: el contador del
     * profesor tiene que incluir también las privadas, igual que el drawer. Las
     * soft-deleted quedan fuera por el scope de SoftDeletes, así que el número
     * siempre coincide con lo que se ve al abrir el historial.
     *
     * @param  array<int, int>  $studentIds
     * @return array<int, int>  [student_id => total]
     */
    public function countsByStudentForPlan(int $planId, array $studentIds): array
    {
        $studentIds = array_values(array_unique(array_map('intval', $studentIds)));

        if ($planId <= 0 || $studentIds === []) {
            return [];
        }

        // Ojo: hay que pasar por `get()` antes de mapear. Un `->pluck()` sobre el
        // builder reemplaza las columnas del `selectRaw` (ver onceWithColumns en
        // Query\Builder::pluck) y el COUNT(*) se rompería.
        return StudentObservation::where('evaluation_plan_id', $planId)
            ->whereIn('student_id', $studentIds)
            ->groupBy('student_id')
            ->selectRaw('student_id, COUNT(*) AS total')
            ->get()
            ->mapWithKeys(fn ($row) => [(int) $row->student_id => (int) $row->total])
            ->all();
    }

    /**
     * Lo único que un representante puede leer: observaciones ya compartidas.
     * Se resuelve en una sola consulta para todos los planes de un estudiante,
     * porque `RepresentativeService::formatSubjects` construye una entrada por
     * materia y consultarlas de a una sería un N+1.
     *
     * @param  array<int, int>  $planIds
     * @return array<int, array<int, array<string, mixed>>>
     */
    public function sharedByStudentGroupedByPlan(int $studentId, array $planIds): array
    {
        $planIds = array_values(array_unique(array_filter(array_map('intval', $planIds))));

        if ($planIds === []) {
            return [];
        }

        return StudentObservation::with(['author', 'plan.matter'])
            ->where('student_id', $studentId)
            ->whereIn('evaluation_plan_id', $planIds)
            ->where('shared_with_representative', true)
            ->orderBy('created_at')
            ->orderBy('id')
            ->get()
            ->groupBy('evaluation_plan_id')
            ->map(fn ($group) => $group
                ->map(fn (StudentObservation $observation) => $this->formatObservation($observation))
                ->values()
                ->all())
            ->all();
    }

    /**
     * Solo el autor o la administración pueden editar/eliminar. La administración
     * necesita además acceso al plan (lo garantiza `GradeAccess::canManagePlan`).
     */
    public function canModify(StudentObservation $observation, ?User $actor): bool
    {
        if ($actor === null || ! GradeAccess::canManagePlan($actor, $observation->plan)) {
            return false;
        }

        if ((int) $observation->created_by === (int) $actor->id) {
            return true;
        }

        return $actor->isTeacher() === false;
    }

    public function formatObservation(StudentObservation $observation, ?User $actor = null): array
    {
        $type = $observation->type instanceof StudentObservationTypeEnum
            ? $observation->type
            : StudentObservationTypeEnum::tryFrom((string) $observation->type);

        $author = $observation->author;
        $matter = $observation->plan?->matter;

        return [
            'id' => $observation->id,
            'type' => $type?->value,
            'type_label' => $type?->label(),
            'type_icon' => $type?->icon(),
            'body' => $observation->body,
            'shared' => (bool) $observation->shared_with_representative,
            'shared_at' => $observation->shared_at?->toISOString(),
            'created_at' => $observation->created_at?->toISOString(),
            'created_at_label' => $observation->created_at?->locale('es')->isoFormat('D [de] MMM [de] YYYY'),
            'author_name' => $author ? trim($author->name.' '.$author->last_name) : 'Coordinación académica',
            'matter_name' => $matter?->name,
            'can_modify' => $actor !== null && $this->canModify($observation, $actor),
        ];
    }

    private function normalizeBody(string $body): string
    {
        $body = trim(preg_replace('/\s+/u', ' ', $body) ?? '');

        return mb_substr($body, 0, self::MAX_BODY_LENGTH);
    }
}
