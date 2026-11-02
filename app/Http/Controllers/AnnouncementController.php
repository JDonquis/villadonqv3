<?php

namespace App\Http\Controllers;

use App\Models\Announcement;
use App\Models\Course;
use App\Models\SchoolEvent;
use App\Models\Section;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Response;

class AnnouncementController extends Controller
{
    public function index(): Response
    {
        return inertia('Dashboard/Comunicados', [
            'announcements' => Announcement::with(['course', 'section'])
                ->orderByDesc('created_at')
                ->get()
                ->map(fn (Announcement $a) => [
                    'id' => $a->id,
                    'title' => $a->title,
                    'body' => $a->body,
                    'audience' => $a->audience,
                    'target_user_types' => $a->target_user_types ?: [1, 2, 3],
                    'course_id' => $a->course_id,
                    'course' => $a->course?->name,
                    'section_id' => $a->section_id,
                    'section' => $a->section?->name,
                    'student_id' => $a->student_id,
                    'status' => $a->status,
                    'published_at' => $a->published_at?->toDateTimeString(),
                ])
                ->values(),
            'events' => SchoolEvent::with(['course', 'section'])
                ->orderByDesc('start_date')
                ->get()
                ->map(fn (SchoolEvent $e) => [
                    'id' => $e->id,
                    'title' => $e->title,
                    'description' => $e->description,
                    'type' => $e->type,
                    'start_date' => $e->start_date?->toDateString(),
                    'end_date' => $e->end_date?->toDateString(),
                    'target_user_types' => $e->target_user_types ?: [1, 2, 3],
                    'course_id' => $e->course_id,
                    'course' => $e->course?->name,
                    'section_id' => $e->section_id,
                    'section' => $e->section?->name,
                    'status' => $e->status,
                ])
                ->values(),
            'courses' => Course::orderBy('id')->get(['id', 'name']),
            'sections' => Section::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validateAnnouncement($request);
        $data['target_user_types'] = $this->targetUserTypes($request);
        $data['created_by'] = auth()->id();
        $data['published_at'] = $request->boolean('published') ? now() : null;
        $data['status'] = $request->boolean('published') ? 1 : 0;

        $announcement = Announcement::create($data);

        if ($announcement->status === 1) {
            $this->notifyAnnouncement($announcement);
        }

        return redirect()->back();
    }

    public function update(Request $request, Announcement $announcement)
    {
        $wasPublished = (int) $announcement->status === 1;

        $data = $this->validateAnnouncement($request);
        $data['target_user_types'] = $this->targetUserTypes($request);
        $data['published_at'] = $request->boolean('published')
            ? ($announcement->published_at ?? now())
            : null;
        $data['status'] = $request->boolean('published') ? 1 : 0;

        $announcement->update($data);

        if (! $wasPublished && (int) $announcement->status === 1) {
            $this->notifyAnnouncement($announcement);
        }

        return redirect()->back();
    }

    public function destroy(Announcement $announcement)
    {
        $announcement->delete();

        return redirect()->back();
    }

    private function validateAnnouncement(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:150'],
            'body' => ['required', 'string'],
            'audience' => ['required', 'in:all,course,section,student'],
            'target_user_types' => ['nullable', 'array'],
            'target_user_types.*' => ['integer', 'in:1,2,3'],
            'course_id' => ['nullable', 'required_if:audience,course', 'exists:courses,id'],
            'section_id' => ['nullable', 'required_if:audience,section', 'exists:sections,id'],
            'student_id' => ['nullable', 'required_if:audience,student', 'exists:students,id'],
        ]);
    }

    /**
     * @return array<int>
     */
    private function targetUserTypes(Request $request): array
    {
        $types = collect($request->input('target_user_types', []))
            ->map(fn ($type) => (int) $type)
            ->filter(fn ($type) => in_array($type, [1, 2, 3], true))
            ->unique()
            ->values()
            ->all();

        return $types === [] ? [1, 2, 3] : $types;
    }

    private function notifyAnnouncement(Announcement $announcement): void
    {
        try {
            app(NotificationService::class)->notifyAnnouncement($announcement, auth()->id());
        } catch (\Throwable $e) {
            Log::error('Error al notificar comunicado '.$announcement->id.': '.$e->getMessage());
        }
    }
}
