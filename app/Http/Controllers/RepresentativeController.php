<?php

namespace App\Http\Controllers;

use App\Models\SchoolLapse;
use App\Services\RepresentativeService;
use App\Services\ScheduleService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Response;

class RepresentativeController extends Controller
{
    private RepresentativeService $representativeService;

    public function __construct()
    {
        $this->representativeService = new RepresentativeService;
    }

    public function misHijos(): Response
    {
        $user = Auth::user();

        return inertia('Dashboard/MisHijos', $this->representativeService->misHijos($user));
    }

    public function horarioHijo(Request $request, int $student): Response
    {
        $user = Auth::user();

        $student = $this->representativeService->getStudents($user)->firstWhere('id', $student);

        abort_unless($student, 404);

        $periods = SchoolLapse::orderBy('start', 'desc')->get()->map(fn ($lapse) => [
            'id' => $lapse->id,
            'name' => $this->formatPeriod($lapse),
            'start' => $lapse->start,
            'end' => $lapse->end,
        ])->values();

        $activeLapse = SchoolLapse::where('status', 1)->latest('id')->first()
            ?? SchoolLapse::orderBy('start', 'desc')->first();

        $schoolLapseId = (int) ($request->input('school_lapse_id') ?? $activeLapse->id ?? 1);

        $scheduleService = new ScheduleService;

        return inertia('Dashboard/HorarioHijo', [
            'data' => [
                'student' => $this->representativeService->formatStudent($student),
                'periods' => $periods,
                'filters' => [
                    'school_lapse_id' => $schoolLapseId,
                ],
                'schedule' => $scheduleService->get(
                    $schoolLapseId,
                    (int) $student->course_id,
                    (int) $student->section_id,
                ),
            ],
        ]);
    }

    private function formatPeriod(SchoolLapse $lapse): string
    {
        $startYear = date('Y', strtotime($lapse->start));
        $endYear = date('Y', strtotime($lapse->end));

        return "$startYear - $endYear";
    }
}
