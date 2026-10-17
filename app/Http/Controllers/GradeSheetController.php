<?php

namespace App\Http\Controllers;

use App\Models\GradeSheet;
use App\Models\MainConfig;
use App\Services\EvaluationPlanService;
use App\Services\GradeSheetService;
use App\Services\StudentGradeService;
use App\Support\ErrorTranslator;
use App\Support\GradeAccess;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class GradeSheetController extends Controller
{
    private GradeSheetService $sheetService;

    private EvaluationPlanService $planService;

    public function __construct()
    {
        $this->sheetService = new GradeSheetService;
        $this->planService = new EvaluationPlanService;
    }

    private function authorizeSheets(): void
    {
        abort_unless(GradeAccess::canManageSheets(auth()->user()), 403);
    }

    public function index(Request $request)
    {
        $this->authorizeSheets();

        $schoolLapseId = (int) ($request->input('school_lapse_id') ?: $this->planService->currentSchoolLapseId());
        $courseId = $request->filled('course_id') ? (int) $request->input('course_id') : null;
        $lapseId = $request->filled('lapse_id') ? (int) $request->input('lapse_id') : null;

        $sheet = $this->sheetService->findSheet($schoolLapseId, $courseId);
        $sheetData = $sheet ? $this->sheetService->getSheetData($sheet, $lapseId) : null;

        return inertia('Dashboard/SabanaNotas', [
            'data' => [
                'sheet' => $sheetData,
                'school_lapses' => $this->planService->getSchoolLapses(),
                'courses' => $this->planService->getCourses(),
                'filters' => [
                    'school_lapse_id' => $schoolLapseId,
                    'course_id' => $courseId,
                    'lapse_id' => $lapseId,
                ],
            ],
        ]);
    }

    public function pdf(Request $request)
    {
        $this->authorizeSheets();

        $schoolLapseId = (int) ($request->input('school_lapse_id') ?: $this->planService->currentSchoolLapseId());
        $courseId = $request->filled('course_id') ? (int) $request->input('course_id') : null;
        $lapseId = $request->filled('lapse_id') ? (int) $request->input('lapse_id') : null;
        $section = $request->input('section') ?: null;

        $sheet = $this->sheetService->findSheet($schoolLapseId, $courseId);

        if (! $sheet) {
            abort(404, 'No hay una sábana de notas para este período escolar y año.');
        }

        $sheetData = $this->sheetService->getSheetData($sheet, $lapseId);
        $matters = $sheetData['matters'];

        $students = collect($sheetData['students'])
            ->when($section, fn ($collection) => $collection->where('section_name', $section))
            ->values()
            ->all();

        // Promedio por estudiante (final con puntos) y promedio previo (definitiva sin puntos).
        foreach ($students as &$student) {
            $finals = [];
            $bases = [];
            foreach ($matters as $matter) {
                $grade = $student['grades'][$matter['id']] ?? null;
                if ($grade === null) {
                    continue;
                }
                if ($grade['final'] !== null) {
                    $finals[] = (float) $grade['final'];
                }
                if ($grade['definitive'] !== null) {
                    $bases[] = (float) $grade['definitive'];
                }
            }
            $student['average'] = $finals ? round(array_sum($finals) / count($finals), 2) : null;
            $student['base_average'] = $bases ? round(array_sum($bases) / count($bases), 2) : null;
        }
        unset($student);

        // Promedio por asignatura y promedio general.
        $matterAverages = [];
        foreach ($matters as $matter) {
            $vals = [];
            foreach ($students as $student) {
                $final = $student['grades'][$matter['id']]['final'] ?? null;
                if ($final !== null) {
                    $vals[] = (float) $final;
                }
            }
            $matterAverages[$matter['id']] = $vals ? round(array_sum($vals) / count($vals), 2) : null;
        }

        $overallValues = array_values(array_filter($matterAverages, fn ($v) => $v !== null));
        $overallAverage = $overallValues ? round(array_sum($overallValues) / count($overallValues), 2) : null;

        $selectedLapse = collect($sheetData['lapses'])
            ->firstWhere('id', $sheetData['selected_lapse_id']);

        $config = MainConfig::first();

        $pdf = Pdf::loadView('reportes.sabana-notas', [
            'institution' => [
                'name' => $config?->name ?? 'INSTITUCIÓN EDUCATIVA',
                'rif' => $config?->rif ? strtoupper($config->rif) : null,
                'address' => $config?->address,
                'phone' => $config?->phone_number,
                'email' => $config?->email,
                'motto' => $config?->motto,
                'logo' => $this->logoDataUri($config?->entity_shield),
            ],
            'sheet' => $sheetData,
            'students' => $students,
            'matters' => $matters,
            'matter_averages' => $matterAverages,
            'overall_average' => $overallAverage,
            'section' => $section,
            'selected_lapse' => $selectedLapse,
            'max_score' => StudentGradeService::MAX_SCORE,
            'passing_score' => StudentGradeService::PASSING_SCORE,
            'generated_at' => Carbon::now()->translatedFormat('d/m/Y H:i'),
        ])->setPaper('a4', 'landscape')->setOption('isRemoteEnabled', true);

        $fileName = 'sabana-notas-'.($sheetData['course']['name'] ?? 'curso').'-'.($selectedLapse['label'] ?? '').'.pdf';
        $fileName = preg_replace('/[^A-Za-z0-9\-_.]+/', '-', $fileName);

        return $pdf->download($fileName);
    }

    public function store(Request $request)
    {
        $this->authorizeSheets();

        $validated = $request->validate([
            'school_lapse_id' => ['required', 'integer', 'exists:school_lapses,id'],
            'course_id' => ['required', 'integer', 'exists:courses,id'],
            'lapse_id' => ['nullable', 'integer', 'exists:lapses,id'],
        ]);

        try {
            $sheet = $this->sheetService->findOrCreate(
                (int) $validated['school_lapse_id'],
                (int) $validated['course_id'],
                (int) auth()->id()
            );

            $params = array_filter([
                'school_lapse_id' => $sheet->school_lapse_id,
                'course_id' => $sheet->course_id,
                'lapse_id' => $validated['lapse_id'] ?? null,
            ]);

            return redirect('/dashboard/sabana-notas?'.http_build_query($params))->with([
                'status' => true,
                'message' => 'Sábana de notas creada correctamente.',
            ]);
        } catch (Exception $e) {
            Log::error('Error al crear sábana de notas: '.$e->getMessage());

            return back()->withErrors(['message' => ErrorTranslator::translate($e)]);
        }
    }

    public function savePoints(Request $request, $id)
    {
        $this->authorizeSheets();

        $sheet = GradeSheet::findOrFail($id);

        $validated = $request->validate([
            'adjustments' => ['present', 'array'],
            'adjustments.*.student_id' => ['required', 'integer'],
            'adjustments.*.matter_id' => ['required', 'integer'],
            'adjustments.*.points' => ['required', 'integer', 'min:0', 'max:'.GradeSheetService::MAX_ADJUSTMENT],
        ]);

        try {
            $saved = $this->sheetService->saveAdjustments($sheet, $validated['adjustments'] ?? []);

            return back()->with([
                'status' => true,
                'message' => "Puntos guardados correctamente ({$saved} registros).",
            ]);
        } catch (Exception $e) {
            Log::error('Error al guardar puntos de sábana ID '.$id.': '.$e->getMessage());

            return back()->withErrors(['message' => ErrorTranslator::translate($e)]);
        }
    }

    public function destroy($id)
    {
        $this->authorizeSheets();

        $sheet = GradeSheet::findOrFail($id);

        try {
            $this->sheetService->deleteSheet($sheet);

            return redirect('/dashboard/sabana-notas')->with([
                'status' => true,
                'message' => 'Sábana de notas eliminada correctamente.',
            ]);
        } catch (Exception $e) {
            Log::error('Error al eliminar sábana de notas ID '.$id.': '.$e->getMessage());

            return back()->withErrors(['message' => ErrorTranslator::translate($e)]);
        }
    }

    private function logoDataUri(?string $shield): ?string
    {
        if (! $shield) {
            return null;
        }

        $path = public_path('storage/'.$shield);

        if (! is_file($path)) {
            return null;
        }

        $mime = @mime_content_type($path) ?: 'image/png';

        return 'data:'.$mime.';base64,'.base64_encode(file_get_contents($path));
    }
}
