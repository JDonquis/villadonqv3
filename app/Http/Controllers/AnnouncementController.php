<?php

namespace App\Http\Controllers;

use App\Models\Announcement;
use App\Models\Course;
use App\Models\SchoolEvent;
use Illuminate\Http\Request;
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
                    'course_id' => $a->course_id,
                    'course' => $a->course?->name,
                    'section_id' => $a->section_id,
                    'section' => $a->section?->name,
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
                    'course_id' => $e->course_id,
                    'course' => $e->course?->name,
                    'section_id' => $e->section_id,
                    'section' => $e->section?->name,
                    'status' => $e->status,
                ])
                ->values(),
            'courses' => Course::orderBy('id')->get(['id', 'name']),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validateAnnouncement($request);
        $data['created_by'] = auth()->id();
        $data['published_at'] = $request->boolean('published') ? now() : null;
        $data['status'] = $request->boolean('published') ? 1 : 0;

        Announcement::create($data);

        return redirect()->back();
    }

    public function update(Request $request, Announcement $announcement)
    {
        $data = $this->validateAnnouncement($request);
        $data['published_at'] = $request->boolean('published')
            ? ($announcement->published_at ?? now())
            : null;
        $data['status'] = $request->boolean('published') ? 1 : 0;

        $announcement->update($data);

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
            'course_id' => ['nullable', 'required_if:audience,course', 'exists:courses,id'],
            'section_id' => ['nullable', 'required_if:audience,section', 'exists:sections,id'],
            'student_id' => ['nullable', 'required_if:audience,student', 'exists:students,id'],
        ]);
    }
}
