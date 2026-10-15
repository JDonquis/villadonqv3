<?php

namespace App\Http\Controllers;

use App\Models\GradeSheet;
use App\Services\EvaluationPlanService;
use App\Services\GradeSheetService;
use App\Support\ErrorTranslator;
use App\Support\GradeAccess;
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
}
