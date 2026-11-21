<?php

namespace App\Services;

use App\Models\Announcement;
use App\Models\EvaluationPlan;
use App\Models\Representative;
use App\Models\Schedule;
use App\Models\SchoolEvent;
use App\Models\SchoolLapse;
use App\Models\Student;
use App\Models\User;
use App\Notifications\AppNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class NotificationService
{
    public function send(User $user, array $payload, ?string $url = null): void
    {
        try {
            $user->notify(new AppNotification($payload, $url));
        } catch (\Throwable $e) {
            Log::error('Error al enviar notificación al usuario '.$user->id.': '.$e->getMessage());
        }
    }

    /**
     * @param  iterable<User>  $users
     * @param  string|callable(User):?string|null  $url
     */
    public function sendToUsers(iterable $users, array $payload, string|callable|null $url = null, ?int $exceptUserId = null): int
    {
        $sent = 0;

        foreach (collect($users)->unique('id') as $user) {
            if (! $user instanceof User) {
                continue;
            }

            if ($exceptUserId !== null && (int) $user->id === $exceptUserId) {
                continue;
            }

            $target = is_callable($url) ? $url($user) : $url;
            $this->send($user, $payload, $target);
            $sent++;
        }

        return $sent;
    }

    /**
     * @param  array<int, array{user:User,url:?string}>  $recipients
     */
    public function sendToRecipients(array $recipients, array $payload, ?int $exceptUserId = null): int
    {
        $sent = 0;
        $seen = [];

        foreach ($recipients as $recipient) {
            $user = $recipient['user'] ?? null;

            if (! $user instanceof User || isset($seen[$user->id])) {
                continue;
            }

            $seen[$user->id] = true;

            if ($exceptUserId !== null && (int) $user->id === $exceptUserId) {
                continue;
            }

            $this->send($user, $payload, $recipient['url'] ?? null);
            $sent++;
        }

        return $sent;
    }

    public function admins(?string $module = null): Collection
    {
        return User::query()
            ->where('type_user_id', 1)
            ->when($module, function ($query) use ($module) {
                $query->where(function ($q) use ($module) {
                    $q->where('is_admin', 1)
                        ->orWhereHas('modules', fn ($modules) => $modules->where('slug', $module));
                });
            })
            ->get();
    }

    /**
     * Representantes de una lista de estudiantes.
     *
     * @param  array<int>  $studentIds
     */
    public function representativesForStudents(array $studentIds): Collection
    {
        $studentIds = array_values(array_unique(array_filter($studentIds)));

        if ($studentIds === []) {
            return collect();
        }

        $representativeIds = Student::whereIn('id', $studentIds)
            ->whereNotNull('representative_id')
            ->pluck('representative_id')
            ->unique()
            ->values();

        if ($representativeIds->isEmpty()) {
            return collect();
        }

        $userIds = Representative::whereIn('id', $representativeIds)->pluck('user_id');

        return User::whereIn('id', $userIds)->get();
    }

    public function representativeOfStudent(int $studentId): ?User
    {
        return Student::with('representative.user')
            ->find($studentId)
            ?->representative
            ?->user;
    }

    public function allRepresentatives(): Collection
    {
        return User::where('type_user_id', 2)
            ->whereHas('representative')
            ->get();
    }

    public function allTeachers(): Collection
    {
        return User::where('type_user_id', 3)->get();
    }

    public function teachersForCourseSection(?int $courseId, ?int $sectionId, ?int $schoolLapseId = null): Collection
    {
        $schoolLapseId ??= $this->activeSchoolLapseId();

        $scheduleQuery = Schedule::query()
            ->when($courseId, fn ($q) => $q->where('course_id', $courseId))
            ->when($sectionId, fn ($q) => $q->where('section_id', $sectionId))
            ->when($schoolLapseId, fn ($q) => $q->where('school_lapse_id', $schoolLapseId));

        $teacherIds = $scheduleQuery->with('classes')->get()
            ->flatMap(fn (Schedule $schedule) => $schedule->classes->pluck('teacher_id'))
            ->filter()
            ->merge(
                EvaluationPlan::query()
                    ->when($courseId, fn ($q) => $q->where('course_id', $courseId))
                    ->when($sectionId, fn ($q) => $q->where('section_id', $sectionId))
                    ->when($schoolLapseId, fn ($q) => $q->where('school_lapse_id', $schoolLapseId))
                    ->pluck('user_id')
            )
            ->unique()
            ->values();

        if ($teacherIds->isEmpty()) {
            return collect();
        }

        return User::whereIn('id', $teacherIds)->where('type_user_id', 3)->get();
    }

    public function activeSchoolLapseId(): ?int
    {
        return SchoolLapse::where('status', 1)->value('id')
            ?? SchoolLapse::orderByDesc('start')->value('id');
    }

    public function notifyAnnouncement(Announcement $announcement, ?int $exceptUserId = null): int
    {
        return $this->notifyAudience(
            $announcement->target_user_types,
            $announcement->audience,
            $announcement->course_id,
            $announcement->section_id,
            $announcement->student_id,
            [
                'type' => 'announcement',
                'title' => 'Nuevo comunicado: '.$announcement->title,
                'body' => $announcement->body,
                'icon' => 'mdi:bullhorn',
                'severity' => 'info',
                'meta' => ['announcement_id' => $announcement->id],
            ],
            $exceptUserId
        );
    }

    public function notifySchoolEvent(SchoolEvent $event, ?int $exceptUserId = null): int
    {
        $body = $event->description ?: 'Actividad programada para el '.$event->start_date?->format('d/m/Y');

        $audience = $event->section_id ? 'section' : ($event->course_id ? 'course' : 'all');

        return $this->notifyAudience(
            $event->target_user_types,
            $audience,
            $event->course_id,
            $event->section_id,
            null,
            [
                'type' => 'school_event',
                'title' => 'Nueva actividad: '.$event->title,
                'body' => $body,
                'icon' => 'mdi:calendar-star',
                'severity' => 'info',
                'meta' => ['event_id' => $event->id],
            ],
            $exceptUserId
        );
    }

    /**
     * @param  array<int>|null  $targetUserTypes
     */
    private function notifyAudience(
        ?array $targetUserTypes,
        string $audience,
        ?int $courseId,
        ?int $sectionId,
        ?int $studentId,
        array $payload,
        ?int $exceptUserId
    ): int {
        $types = $targetUserTypes ?: [1, 2, 3];
        $recipients = [];

        if (in_array(1, $types, true)) {
            foreach ($this->admins('comunicados') as $admin) {
                $recipients[] = ['user' => $admin, 'url' => '/dashboard/comunicados'];
            }
        }

        if (in_array(2, $types, true)) {
            foreach ($this->representativesForAudience($audience, $courseId, $sectionId, $studentId) as $representative) {
                $recipients[] = ['user' => $representative, 'url' => '/dashboard/inicio'];
            }
        }

        if (in_array(3, $types, true)) {
            foreach ($this->teachersForAudience($audience, $courseId, $sectionId, $studentId) as $teacher) {
                $recipients[] = ['user' => $teacher, 'url' => '/dashboard/notificaciones'];
            }
        }

        return $this->sendToRecipients($recipients, $payload, $exceptUserId);
    }

    private function representativesForAudience(string $audience, ?int $courseId, ?int $sectionId, ?int $studentId): Collection
    {
        if ($audience === 'all') {
            return $this->allRepresentatives();
        }

        $studentIds = match ($audience) {
            'course' => Student::where('course_id', $courseId)->where('status', '!=', 0)->pluck('id')->all(),
            'section' => Student::where('section_id', $sectionId)->where('status', '!=', 0)->pluck('id')->all(),
            'student' => $studentId ? [$studentId] : [],
            default => [],
        };

        return $this->representativesForStudents($studentIds);
    }

    private function teachersForAudience(string $audience, ?int $courseId, ?int $sectionId, ?int $studentId): Collection
    {
        if ($audience === 'all') {
            return $this->allTeachers();
        }

        if ($audience === 'student' && $studentId) {
            $student = Student::find($studentId);

            return $student
                ? $this->teachersForCourseSection($student->course_id, $student->section_id)
                : collect();
        }

        return $this->teachersForCourseSection(
            $audience === 'course' ? $courseId : null,
            $audience === 'section' ? $sectionId : null
        );
    }
}
