<?php

namespace App\Services;

use App\Models\EvaluationPlan;
use App\Models\EvaluationPlanAttendanceSession;
use App\Models\Student;
use App\Models\StudentAttendance;

class AttendanceService
{
    public function getAttendanceMatrix(int $planId): array
    {
        $plan = EvaluationPlan::with(['items', 'course', 'section'])->findOrFail($planId);

        // Get sessions ordered by date
        $sessions = EvaluationPlanAttendanceSession::where('evaluation_plan_id', $planId)
            ->ordered()
            ->get()
            ->map(function ($session) {
                return [
                    'id' => $session->id,
                    'date' => $session->date->toDateString(),
                    'order' => $session->order,
                    'day_of_week' => ucfirst(str_replace('.', '', $session->date->locale('es')->isoFormat('ddd'))),
                ];
            })
            ->values()
            ->all();

        // Get students in this plan's course/section
        $students = Student::where('course_id', $plan->course_id)
            ->where('section_id', $plan->section_id)
            ->where('status', '!=', 0)
            ->orderBy('last_name')
            ->orderBy('name')
            ->get()
            ->map(function ($student) {
                return [
                    'id' => $student->id,
                    'name' => $student->name,
                    'last_name' => $student->last_name,
                    'ci' => $student->ci,
                ];
            })
            ->values()
            ->all();

        // Get all attendances for this plan's sessions
        $sessionIds = collect($sessions)->pluck('id');
        $attendances = StudentAttendance::whereIn('session_id', $sessionIds)
            ->get()
            ->groupBy('session_id')
            ->map(function ($items) {
                return $items->mapWithKeys(function ($attendance) {
                    return [$attendance->student_id => $attendance->status];
                })->all();
            })
            ->all();

        return [
            'plan' => [
                'id' => $plan->id,
                'name' => $plan->name,
                'matter_name' => $plan->matter?->name,
                'course_name' => $plan->course?->name,
                'section_name' => $plan->section?->name,
            ],
            'sessions' => $sessions,
            'students' => $students,
            'attendance' => $attendances,
        ];
    }

    /**
     * Asistencia acumulada por alumno dentro de un período/momento. Un alumno
     * sin registro en una sesión que le aplica se considera ausente.
     *
     * @param  array<int>  $studentIds
     * @return array<int, array{present:int,excused:int,absent:int,total:int,rate:?float}>
     */
    public function getAccumulatedForStudents(array $studentIds, ?int $schoolLapseId = null, ?int $lapseId = null): array
    {
        $studentIds = array_values(array_unique(array_filter($studentIds)));

        if (empty($studentIds)) {
            return [];
        }

        $students = Student::whereIn('id', $studentIds)->get(['id', 'course_id', 'section_id']);

        $plans = EvaluationPlan::query()
            ->where('status', 'approved')
            ->when($schoolLapseId, fn ($q) => $q->where('school_lapse_id', $schoolLapseId))
            ->when($lapseId, fn ($q) => $q->where('lapse_id', $lapseId))
            ->get(['id', 'course_id', 'section_id']);

        $sessionsByPlan = EvaluationPlanAttendanceSession::whereIn('evaluation_plan_id', $plans->pluck('id'))
            ->get(['id', 'evaluation_plan_id'])
            ->groupBy('evaluation_plan_id');

        $planMeta = $plans->mapWithKeys(fn ($plan) => [
            $plan->id => ['course_id' => $plan->course_id, 'section_id' => $plan->section_id],
        ]);

        $allSessionIds = $sessionsByPlan->flatten()->pluck('id');

        $counts = StudentAttendance::whereIn('session_id', $allSessionIds)
            ->whereIn('student_id', $studentIds)
            ->selectRaw('student_id, status, COUNT(*) as total')
            ->groupBy('student_id', 'status')
            ->get()
            ->groupBy('student_id');

        $result = [];

        foreach ($students as $student) {
            $applicableSessions = 0;

            foreach ($sessionsByPlan as $planId => $sessions) {
                $meta = $planMeta[$planId] ?? null;

                if (! $meta) {
                    continue;
                }

                if ((int) $meta['course_id'] === (int) $student->course_id
                    && (int) $meta['section_id'] === (int) $student->section_id) {
                    $applicableSessions += $sessions->count();
                }
            }

            $byStatus = $counts->get($student->id, collect());
            $present = (int) ($byStatus->firstWhere('status', 'present')?->total ?? 0);
            $excused = (int) ($byStatus->firstWhere('status', 'excused')?->total ?? 0);
            $absent = max(0, $applicableSessions - $present - $excused);

            $result[$student->id] = [
                'present' => $present,
                'excused' => $excused,
                'absent' => $absent,
                'total' => $applicableSessions,
                'rate' => $applicableSessions > 0 ? round(($present / $applicableSessions) * 100, 1) : null,
            ];
        }

        return $result;
    }

    public function getOrCreateSession(int $planId, string $date): EvaluationPlanAttendanceSession
    {
        $session = EvaluationPlanAttendanceSession::where('evaluation_plan_id', $planId)
            ->where('date', $date)
            ->first();

        if ($session) {
            return $session;
        }

        return $this->createSession($planId, $date);
    }

    public function createSession(int $planId, string $date): EvaluationPlanAttendanceSession
    {
        // Get max order for this plan
        $maxOrder = EvaluationPlanAttendanceSession::where('evaluation_plan_id', $planId)
            ->max('order') ?? 0;

        return EvaluationPlanAttendanceSession::create([
            'evaluation_plan_id' => $planId,
            'date' => $date,
            'order' => $maxOrder + 1,
        ]);
    }

    public function deleteSession(int $sessionId): void
    {
        $session = EvaluationPlanAttendanceSession::findOrFail($sessionId);

        // Cascade will delete attendances, but let's be explicit
        StudentAttendance::where('session_id', $sessionId)->delete();
        $session->delete();
    }

    /**
     * @param  array  $records  [{session_id, student_id, status}, ...]
     */
    public function saveAttendance(int $planId, array $records): int
    {
        $sessionIds = EvaluationPlanAttendanceSession::where('evaluation_plan_id', $planId)
            ->pluck('id')
            ->all();

        $studentIds = Student::where('course_id', function ($q) use ($planId) {
            $q->select('course_id')->from('evaluation_plans')->where('id', $planId);
        })
            ->where('section_id', function ($q) use ($planId) {
                $q->select('section_id')->from('evaluation_plans')->where('id', $planId);
            })
            ->where('status', '!=', 0)
            ->pluck('id')
            ->all();

        $saved = 0;
        $absenceSessions = [];

        foreach ($records as $record) {
            $sessionId = (int) ($record['session_id'] ?? 0);
            $studentId = (int) ($record['student_id'] ?? 0);
            $status = $record['status'] ?? 'absent';

            if (! in_array($sessionId, $sessionIds, true)) {
                continue;
            }
            if (! in_array($studentId, $studentIds, true)) {
                continue;
            }
            if (! in_array($status, ['absent', 'present', 'excused'], true)) {
                continue;
            }

            $attendance = StudentAttendance::updateOrCreate(
                ['session_id' => $sessionId, 'student_id' => $studentId],
                ['status' => $status]
            );

            if ($status === 'absent' && $attendance->notified_at === null) {
                $attendance->update(['notified_at' => now()]);
                $absenceSessions[$studentId][] = $sessionId;
            } elseif ($status !== 'absent' && $attendance->notified_at !== null) {
                $attendance->update(['notified_at' => null]);
            }

            $saved++;
        }

        $this->notifyAbsences($absenceSessions);

        return $saved;
    }

    public function toggleAttendance(int $sessionId, int $studentId): string
    {
        $attendance = StudentAttendance::where('session_id', $sessionId)
            ->where('student_id', $studentId)
            ->first();

        $statuses = ['absent', 'present', 'excused'];
        $currentIndex = $attendance ? array_search($attendance->status, $statuses) : -1;
        $nextIndex = ($currentIndex + 1) % count($statuses);
        $nextStatus = $statuses[$nextIndex];

        $attendance = StudentAttendance::updateOrCreate(
            ['session_id' => $sessionId, 'student_id' => $studentId],
            ['status' => $nextStatus]
        );

        if ($nextStatus === 'absent' && $attendance->notified_at === null) {
            $attendance->update(['notified_at' => now()]);
            $this->notifyAbsences([$studentId => [$sessionId]]);
        } elseif ($nextStatus !== 'absent' && $attendance->notified_at !== null) {
            $attendance->update(['notified_at' => null]);
        }

        return $nextStatus;
    }

    /**
     * @param  array<int, array<int>>  $sessionsByStudent
     */
    private function notifyAbsences(array $sessionsByStudent): void
    {
        if ($sessionsByStudent === []) {
            return;
        }

        $students = Student::with('representative.user')
            ->whereIn('id', array_keys($sessionsByStudent))
            ->get()
            ->keyBy('id');

        $sessionIds = collect($sessionsByStudent)->flatten()->unique()->values();
        $sessions = EvaluationPlanAttendanceSession::with('plan.matter')
            ->whereIn('id', $sessionIds)
            ->get()
            ->keyBy('id');

        $service = app(NotificationService::class);

        foreach ($sessionsByStudent as $studentId => $studentSessions) {
            $student = $students->get((int) $studentId);
            $representative = $student?->representative?->user;

            if (! $student || ! $representative) {
                continue;
            }

            $relatedSessions = collect($studentSessions)
                ->map(fn ($id) => $sessions->get($id))
                ->filter();

            $firstSession = $relatedSessions->first();
            $matter = $firstSession?->plan?->matter?->name ?? 'la materia';
            $dates = $relatedSessions
                ->pluck('date')
                ->filter()
                ->map(fn ($date) => $date->format('d/m/Y'))
                ->unique()
                ->implode(', ');

            $service->send(
                $representative,
                [
                    'type' => 'attendance_absent',
                    'title' => 'Falta registrada',
                    'body' => "Se registró una falta de {$student->name} {$student->last_name} en {$matter} el {$dates}.",
                    'icon' => 'mdi:account-alert-outline',
                    'severity' => 'warning',
                    'meta' => [
                        'student_id' => $student->id,
                        'session_ids' => $studentSessions,
                    ],
                ],
                '/dashboard/inicio'
            );
        }
    }
}
