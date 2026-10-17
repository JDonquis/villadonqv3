<?php

namespace App\Http\Controllers;

use App\Services\DocumentExcelService;
use App\Services\DocumentService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class DocumentController extends Controller
{
    private DocumentService $documentService;

    private DocumentExcelService $excelService;

    public function __construct()
    {
        $this->documentService = new DocumentService;
        $this->excelService = new DocumentExcelService;
    }

    public function index()
    {
        $options = $this->documentService->filterOptions();
        $graduates = $this->documentService->getRegistroTitulos($options['current_school_lapse_id'], 1, null);

        return inertia('Dashboard/Documentos/Index', [
            'options' => $options,
            'summary' => [
                'students' => count($this->documentService->studentOptions()),
                'graduates' => $graduates['meta']['total'],
            ],
        ]);
    }

    public function sabanaAcademica(Request $request)
    {
        $filters = $this->sabanaFilters($request);
        $data = $this->documentService->getSabanaAcademica(
            $filters['school_lapse_id'],
            $filters['course_id'],
            $filters['section_id'],
            $filters['lapse_id']
        );

        return inertia('Dashboard/Documentos/SabanaAcademica', [
            'data' => $data,
            'filters' => $filters,
            'options' => $this->documentService->filterOptions(),
        ]);
    }

    public function sabanaAcademicaExcel(Request $request)
    {
        $filters = $this->sabanaFilters($request);
        $data = $this->documentService->getSabanaAcademica($filters['school_lapse_id'], $filters['course_id'], $filters['section_id'], $filters['lapse_id']);

        return $this->excelService->sabanaAcademica($data);
    }

    public function sabanaAcademicaPdf(Request $request)
    {
        $filters = $this->sabanaFilters($request);
        $data = $this->documentService->getSabanaAcademica($filters['school_lapse_id'], $filters['course_id'], $filters['section_id'], $filters['lapse_id']);

        return Pdf::loadView('reportes.documentos.sabana-academica', ['data' => $data])
            ->setPaper('legal', 'landscape')
            ->setOption('isRemoteEnabled', true)
            ->download('sabana-academica-'.$this->slug($data['meta']['course_name']).'.pdf');
    }

    public function resumenFinal(Request $request)
    {
        $filters = $this->resumenFilters($request);
        $data = $this->documentService->getResumenFinal($filters['school_lapse_id'], $filters['course_id'], $filters['section_id']);

        return inertia('Dashboard/Documentos/ResumenFinal', [
            'data' => $data,
            'filters' => $filters,
            'options' => $this->documentService->filterOptions(),
        ]);
    }

    public function resumenFinalExcel(Request $request)
    {
        $filters = $this->resumenFilters($request);
        $data = $this->documentService->getResumenFinal($filters['school_lapse_id'], $filters['course_id'], $filters['section_id']);

        return $this->excelService->resumenFinal($data);
    }

    public function resumenFinalPdf(Request $request)
    {
        $filters = $this->resumenFilters($request);
        $data = $this->documentService->getResumenFinal($filters['school_lapse_id'], $filters['course_id'], $filters['section_id']);

        return Pdf::loadView('reportes.documentos.resumen-final', ['data' => $data])
            ->setPaper('legal', 'landscape')
            ->setOption('isRemoteEnabled', true)
            ->download('resumen-final-'.$this->slug($data['meta']['course_name']).'.pdf');
    }

    public function certificadoNotas(Request $request)
    {
        $options = $this->documentService->filterOptions();
        $students = $this->documentService->studentOptions();
        $studentId = (int) $request->input('student_id');

        if (! $studentId && ! empty($students)) {
            $studentId = (int) $students[0]['id'];
        }

        $data = $studentId ? $this->documentService->getCertificadoNotas($studentId) : null;

        return inertia('Dashboard/Documentos/CertificadoNotas', [
            'data' => $data,
            'student_id' => $studentId,
            'students' => $students,
            'options' => $options,
        ]);
    }

    public function certificadoNotasExcel(Request $request)
    {
        $studentId = (int) $request->input('student_id');
        abort_unless($studentId, 404, 'Debe seleccionar un estudiante.');

        return $this->excelService->certificadoNotas($this->documentService->getCertificadoNotas($studentId));
    }

    public function certificadoNotasPdf(Request $request)
    {
        $studentId = (int) $request->input('student_id');
        abort_unless($studentId, 404, 'Debe seleccionar un estudiante.');

        $data = $this->documentService->getCertificadoNotas($studentId);

        return Pdf::loadView('reportes.documentos.certificado-notas', ['data' => $data])
            ->setPaper('legal', 'portrait')
            ->setOption('isRemoteEnabled', true)
            ->download('certificado-notas-'.$this->slug($data['student']['ci']).'.pdf');
    }

    public function registroTitulos(Request $request)
    {
        $filters = $this->titulosFilters($request);
        $data = $this->documentService->getRegistroTitulos(
            $filters['school_lapse_id'],
            $filters['course_id'],
            $filters['section_id'],
            $filters['search']
        );

        return inertia('Dashboard/Documentos/RegistroTitulos', [
            'data' => $data,
            'filters' => $filters,
            'options' => $this->documentService->filterOptions(),
        ]);
    }

    public function registroTitulosExcel(Request $request)
    {
        $filters = $this->titulosFilters($request);
        $data = $this->documentService->getRegistroTitulos($filters['school_lapse_id'], $filters['course_id'], $filters['section_id'], $filters['search']);

        return $this->excelService->registroTitulos($data);
    }

    public function registroTitulosPdf(Request $request)
    {
        $filters = $this->titulosFilters($request);
        $data = $this->documentService->getRegistroTitulos($filters['school_lapse_id'], $filters['course_id'], $filters['section_id'], $filters['search']);

        return Pdf::loadView('reportes.documentos.registro-titulos', ['data' => $data])
            ->setPaper('legal', 'portrait')
            ->setOption('isRemoteEnabled', true)
            ->download('registro-titulos-'.$this->slug($data['meta']['course_name']).'.pdf');
    }

    public function generarSeriales(Request $request)
    {
        $validated = $request->validate([
            'school_lapse_id' => ['required', 'integer', 'exists:school_lapses,id'],
            'course_id' => ['required', 'integer', 'exists:courses,id'],
            'section_id' => ['nullable', 'integer', 'exists:sections,id'],
            'prefix' => ['required', 'string', 'max:6'],
            'start' => ['required', 'integer', 'min:1'],
        ]);

        $result = $this->documentService->generateTitleSerials(
            (int) $validated['school_lapse_id'],
            (int) $validated['course_id'],
            $validated['section_id'] ? (int) $validated['section_id'] : null,
            $validated['prefix'],
            (int) $validated['start']
        );

        return back()->with([
            'status' => true,
            'message' => 'Seriales generados: '.$result['updated'].'. Próximo serial: '.$result['next'].'.',
        ]);
    }

    public function actualizarSerial(Request $request, int $student)
    {
        $validated = $request->validate([
            'title_serial' => ['nullable', 'string', 'max:30'],
            'title_observations' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $this->documentService->updateTitleSerial(
                $student,
                $validated['title_serial'] ?? null,
                $validated['title_observations'] ?? null
            );
        } catch (\RuntimeException $exception) {
            return back()->withErrors(['message' => $exception->getMessage()]);
        }

        return back()->with([
            'status' => true,
            'message' => 'Serial del título actualizado correctamente.',
        ]);
    }

    private function sabanaFilters(Request $request): array
    {
        $options = $this->documentService->filterOptions();
        $schoolLapseId = (int) ($request->input('school_lapse_id') ?: $options['current_school_lapse_id']);

        return [
            'school_lapse_id' => $schoolLapseId,
            'course_id' => $request->filled('course_id') ? (int) $request->input('course_id') : 1,
            'section_id' => $request->filled('section_id') ? (int) $request->input('section_id') : null,
            'lapse_id' => $request->filled('lapse_id') ? (int) $request->input('lapse_id') : null,
        ];
    }

    private function resumenFilters(Request $request): array
    {
        $filters = $this->sabanaFilters($request);
        unset($filters['lapse_id']);

        return $filters;
    }

    private function titulosFilters(Request $request): array
    {
        $options = $this->documentService->filterOptions();

        return [
            'school_lapse_id' => (int) ($request->input('school_lapse_id') ?: $options['current_school_lapse_id']),
            'course_id' => (int) ($request->input('course_id') ?: 1),
            'section_id' => $request->filled('section_id') ? (int) $request->input('section_id') : null,
            'search' => $request->input('search'),
        ];
    }

    private function slug(?string $value): string
    {
        return Str::slug($value ?: 'documento');
    }
}
