<?php

namespace App\Http\Controllers;

use App\Models\FailedImport;
use App\Services\StudentService;
use App\Support\ErrorTranslator;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class StudentImportFailedController extends Controller
{
    private StudentService $studentService;

    public function __construct()
    {
        $this->studentService = new StudentService;
    }

    public function index(Request $request)
    {
        $failedImports = FailedImport::orderBy('created_at', 'desc')->get();

        return inertia('Dashboard/ImportacionesFallidas', [
            'data' => [
                'failedImports' => $failedImports->map(function ($fi) {
                    return [
                        'id' => $fi->id,
                        'row_number' => $fi->row_number,
                        'data' => $fi->data,
                        'error_message' => $fi->error_message,
                        'created_at' => $fi->created_at->format('Y-m-d H:i'),
                    ];
                }),
                'filters' => [
                    'search' => $request->input('search') ?? null,
                ],
            ],
        ]);
    }

    public function update(Request $request, $id)
    {
        $failedImport = FailedImport::findOrFail($id);

        $validated = $request->validate([
            'student_name' => 'sometimes|string|max:255',
            'student_last_name' => 'sometimes|string|max:255',
            'student_ci' => 'sometimes|string|max:20',
            'student_date_birth' => 'sometimes|nullable|date',
            'rep_name' => 'sometimes|string|max:255',
            'rep_last_name' => 'sometimes|string|max:255',
            'rep_ci' => 'sometimes|string|max:20',
            'rep_email' => 'sometimes|nullable|email',
            'rep_phone_number' => 'sometimes|nullable|string',
            'student_email' => 'sometimes|nullable|email',
            'student_phone_number' => 'sometimes|nullable|string',
            'student_sex' => 'sometimes|nullable|in:Masculino,Femenino',
            'student_previous_school' => 'sometimes|nullable|string',
            'course_name' => 'sometimes|nullable|string',
            'section_name' => 'sometimes|nullable|string',
            'is_exempt' => 'sometimes|boolean',
            'exemption_percentage' => 'sometimes|nullable|integer|min:1|max:100',
            'exemption_observations' => 'sometimes|nullable|string',
            'rep_profession' => 'sometimes|nullable|string',
            'rep_workplace' => 'sometimes|nullable|string',
            'rep_relationship' => 'sometimes|nullable|string',
            'second_rep_name' => 'sometimes|nullable|string',
            'second_rep_last_name' => 'sometimes|nullable|string',
            'second_rep_ci' => 'sometimes|nullable|string',
            'second_rep_relationship' => 'sometimes|nullable|string',
        ]);

        $data = $failedImport->data;
        foreach ($validated as $key => $value) {
            if ($value !== null && $value !== '') {
                $data[$key] = $value;
            }
        }

        $failedImport->update(['data' => $data]);

        return response()->json(['success' => true, 'data' => $data]);
    }

    public function retry($id)
    {
        try {
            $this->studentService->retryImport($id);

            return response()->json(['success' => true]);
        } catch (Exception $e) {
            Log::error('Error al reintentar importación fallida ID '.$id.': '.$e->getMessage());

            return response()->json(['success' => false, 'error' => ErrorTranslator::translate($e)], 422);
        }
    }

    public function destroy($id)
    {
        $failedImport = FailedImport::findOrFail($id);
        $failedImport->delete();

        return response()->json(['success' => true]);
    }
}
