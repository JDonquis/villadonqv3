<?php

namespace App\Http\Controllers;

use App\Models\SchoolEvent;
use Illuminate\Http\Request;

class SchoolEventController extends Controller
{
    public function store(Request $request)
    {
        $data = $this->validateEvent($request);
        $data['created_by'] = auth()->id();
        $data['published_at'] = $request->boolean('published') ? now() : null;
        $data['status'] = $request->boolean('published') ? 1 : 0;

        SchoolEvent::create($data);

        return redirect()->back();
    }

    public function update(Request $request, SchoolEvent $event)
    {
        $data = $this->validateEvent($request);
        $data['published_at'] = $request->boolean('published')
            ? ($event->published_at ?? now())
            : null;
        $data['status'] = $request->boolean('published') ? 1 : 0;

        $event->update($data);

        return redirect()->back();
    }

    public function destroy(SchoolEvent $event)
    {
        $event->delete();

        return redirect()->back();
    }

    private function validateEvent(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string'],
            'type' => ['required', 'in:general,exam,meeting,holiday'],
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'course_id' => ['nullable', 'exists:courses,id'],
            'section_id' => ['nullable', 'exists:sections,id'],
        ]);
    }
}
