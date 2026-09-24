<?php

namespace App\Services;

use App\Models\EvaluationPlan;
use App\Models\EvaluationPlanAttendanceSession;
use App\Models\Student;
use App\Models\StudentAttendance;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

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
     * @param array $records [{session_id, student_id, status}, ...]
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
        foreach ($records as $record) {
            $sessionId = (int) ($record['session_id'] ?? 0);
            $studentId = (int) ($record['student_id'] ?? 0);
            $status = $record['status'] ?? 'absent';

            if (!in_array($sessionId, $sessionIds, true)) continue;
            if (!in_array($studentId, $studentIds, true)) continue;
            if (!in_array($status, ['absent', 'present', 'excused'], true)) continue;

            StudentAttendance::updateOrCreate(
                ['session_id' => $sessionId, 'student_id' => $studentId],
                ['status' => $status]
            );

            $saved++;
        }

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

        StudentAttendance::updateOrCreate(
            ['session_id' => $sessionId, 'student_id' => $studentId],
            ['status' => $nextStatus]
        );

        return $nextStatus;
    }
}