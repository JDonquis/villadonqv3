<?php

namespace App\Http\Controllers;

use App\Models\SchoolEvent;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class SchoolEventController extends Controller
{
    public function store(Request $request)
    {
        $data = $this->validateEvent($request);
        $data['target_user_types'] = $this->targetUserTypes($request);
        $data['created_by'] = auth()->id();
        $data['published_at'] = $request->boolean('published') ? now() : null;
        $data['status'] = $request->boolean('published') ? 1 : 0;

        $event = SchoolEvent::create($data);

        if ($event->status === 1) {
            $this->notifyEvent($event);
        }

        return redirect()->back();
    }

    public function update(Request $request, SchoolEvent $event)
    {
        $wasPublished = (int) $event->status === 1;

        $data = $this->validateEvent($request);
        $data['target_user_types'] = $this->targetUserTypes($request);
        $data['published_at'] = $request->boolean('published')
            ? ($event->published_at ?? now())
            : null;
        $data['status'] = $request->boolean('published') ? 1 : 0;

        $event->update($data);

        if (! $wasPublished && (int) $event->status === 1) {
            $this->notifyEvent($event);
        }

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
            'target_user_types' => ['nullable', 'array'],
            'target_user_types.*' => ['integer', 'in:1,2,3'],
            'course_id' => ['nullable', 'exists:courses,id'],
            'section_id' => ['nullable', 'exists:sections,id'],
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

    private function notifyEvent(SchoolEvent $event): void
    {
        try {
            app(NotificationService::class)->notifySchoolEvent($event, auth()->id());
        } catch (\Throwable $e) {
            Log::error('Error al notificar evento '.$event->id.': '.$e->getMessage());
        }
    }
}
