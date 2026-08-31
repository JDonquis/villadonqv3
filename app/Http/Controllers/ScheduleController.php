<?php

namespace App\Http\Controllers;

use App\Models\Schedule;
use App\Services\ScheduleService;
use App\Support\ErrorTranslator;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ScheduleController extends Controller
{
    private ScheduleService $scheduleService;

    public function __construct()
    {
        $this->scheduleService = new ScheduleService;
    }

    public function index(Request $request)
    {
        $data = $this->scheduleService->getIndexData();

        $schoolLapseId = (int) ($request->input('school_lapse_id') ?? $data['periods']->first()->id ?? 1);
        $courseId = (int) ($request->input('course_id') ?? 1);
        $sectionId = (int) ($request->input('section_id') ?? 1);

        $schedule = Schedule::where('school_lapse_id', $schoolLapseId)
            ->where('course_id', $courseId)
            ->where('section_id', $sectionId)
            ->first();

        return inertia('Dashboard/Horarios', [
            'data' => array_merge($data, [
                'filters' => [
                    'school_lapse_id' => $schoolLapseId,
                    'course_id' => $courseId,
                    'section_id' => $sectionId,
                ],
                'schedule' => $this->scheduleService->get($schoolLapseId, $courseId, $sectionId),
                'occupancy' => $this->scheduleService->teacherOccupancy($schoolLapseId, $schedule?->id),
            ]),
        ]);
    }

    public function store(Request $request)
    {
        try {
            $schedule = $this->scheduleService->save($request->all());

            return back()->with([
                'status' => true,
                'message' => 'Horario guardado exitosamente.',
            ]);
        } catch (Exception $e) {
            Log::error('Error al guardar horario: '.$e->getMessage());

            return back()->withErrors([
                'status' => false,
                'message' => ErrorTranslator::translate($e),
            ]);
        }
    }
}
