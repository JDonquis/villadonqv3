<?php

namespace App\Services;

use App\Models\Course;
use App\Models\Quota;
use App\Models\SchoolLapse;

class QuotaService
{
    public function activeSchoolLapseId(): ?int
    {
        $id = SchoolLapse::where('status', 1)->value('id');

        return $id !== null ? (int) $id : null;
    }

    public function quotaFor(int $courseId, ?int $schoolLapseId = null): ?Quota
    {
        $schoolLapseId = $schoolLapseId ?? $this->activeSchoolLapseId();
        if (! $schoolLapseId) {
            return null;
        }

        return Quota::where('school_lapse_id', $schoolLapseId)
            ->where('course_id', $courseId)
            ->first();
    }

    public function quotasForPeriod(?int $schoolLapseId = null): array
    {
        $schoolLapseId = $schoolLapseId ?? $this->activeSchoolLapseId();

        return Course::orderBy('id')
            ->get()
            ->map(function (Course $course) use ($schoolLapseId) {
                $quota = null;
                if ($schoolLapseId) {
                    $quota = Quota::where('school_lapse_id', $schoolLapseId)
                        ->where('course_id', $course->id)
                        ->first();
                }

                return [
                    'course_id' => $course->id,
                    'name' => $course->name,
                    'assigned' => $quota ? (int) $quota->assigned : null,
                    'accepted' => $quota ? (int) $quota->accepted : 0,
                    'remaining' => $quota ? max(0, (int) $quota->remaining) : null,
                    'has_quota' => (bool) $quota,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * Lanza una excepción si el curso del periodo activo ya alcanzó su cupo
     * (accepted >= assigned). Sin fila de cupo configurada se permite (legacy);
     * el admin la crea desde Configuración -> Cupos.
     */
    public function assertCapacity(int $courseId): void
    {
        $quota = $this->quotaFor($courseId);
        if (! $quota) {
            return;
        }

        if ((int) $quota->accepted >= (int) $quota->assigned) {
            $courseName = Course::where('id', $courseId)->value('name');
            $label = $courseName ?: "curso #{$courseId}";

            throw new \Exception("El año escolar {$label} alcanzó su cupo ({$quota->accepted} de {$quota->assigned}).");
        }
    }

    public function occupy(int $courseId, ?int $schoolLapseId = null): void
    {
        $quota = $this->quotaFor($courseId, $schoolLapseId);
        if (! $quota) {
            return;
        }

        $quota->increment('accepted');
        $quota->refresh();
        $quota->update(['remaining' => max(0, (int) $quota->assigned - (int) $quota->accepted)]);
    }

    public function release(int $courseId, ?int $schoolLapseId = null): void
    {
        $quota = $this->quotaFor($courseId, $schoolLapseId);
        if (! $quota) {
            return;
        }

        if ($quota->accepted > 0) {
            $quota->decrement('accepted');
        }
        $quota->refresh();
        $quota->update(['remaining' => max(0, (int) $quota->assigned - (int) $quota->accepted)]);
    }

    /**
     * Guarda la capacidad (assigned) por curso para un periodo. Crea la fila de
     * cupo si no existe y deja remaining coherente (no toca accepted).
     */
    public function saveAssigned(array $courseToAssigned, ?int $schoolLapseId = null): void
    {
        $schoolLapseId = $schoolLapseId ?? $this->activeSchoolLapseId();
        if (! $schoolLapseId) {
            return;
        }

        foreach ($courseToAssigned as $courseId => $assigned) {
            $courseId = (int) $courseId;
            $assigned = max(0, (int) $assigned);

            $quota = Quota::firstOrCreate(
                [
                    'school_lapse_id' => $schoolLapseId,
                    'course_id' => $courseId,
                ],
                [
                    'assigned' => $assigned,
                    'accepted' => 0,
                    'remaining' => $assigned,
                ]
            );

            $quota->update([
                'assigned' => $assigned,
                'remaining' => max(0, $assigned - (int) $quota->accepted),
            ]);
        }
    }
}
