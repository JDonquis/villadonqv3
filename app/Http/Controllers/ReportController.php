<?php

namespace App\Http\Controllers;

use App\Services\ReportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ReportController extends Controller
{
    private ReportService $reportService;

    public function __construct()
    {
        $this->reportService = new ReportService;
    }

    public function boleta(Request $request, $studentId)
    {
        try {
            $data = $this->reportService->getStudentReportData((int) $studentId, $request->input('lapse_id'));

            $pdf = Pdf::loadView('reportes.boleta', $data)
                ->setPaper('letter')
                ->setOption('isRemoteEnabled', true);

            return $pdf->download('boleta_'.$data['student']['ci'].'.pdf');
        } catch (Exception $e) {
            Log::error('Error al generar boleta del estudiante '.$studentId.': '.$e->getMessage());

            abort(500, 'No se pudo generar la boleta. Verifique que el estudiante tenga datos de notas.');
        }
    }

    public function certificado(Request $request, $studentId)
    {
        try {
            $data = $this->reportService->getStudentReportData((int) $studentId, $request->input('lapse_id'));

            $pdf = Pdf::loadView('reportes.certificado', $data)
                ->setPaper('letter')
                ->setOption('isRemoteEnabled', true);

            return $pdf->download('certificado_'.$data['student']['ci'].'.pdf');
        } catch (Exception $e) {
            Log::error('Error al generar certificado del estudiante '.$studentId.': '.$e->getMessage());

            abort(500, 'No se pudo generar el certificado. Verifique que el estudiante tenga datos de notas.');
        }
    }
}
