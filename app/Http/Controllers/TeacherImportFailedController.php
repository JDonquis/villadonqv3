<?php

namespace App\Http\Controllers;

use App\Models\FailedImport;
use App\Services\TeacherService;
use App\Support\ErrorTranslator;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class TeacherImportFailedController extends Controller
{
    private TeacherService $teacherService;

    public function __construct()
    {
        $this->teacherService = new TeacherService;
    }

    public function index(Request $request)
    {
        $failedImports = FailedImport::orderBy('created_at', 'desc')->get();

        return inertia('Dashboard/ImportacionesFallidas', [
            'data' => [
                'importType' => 'teacher',
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
            'ci' => 'sometimes|string|max:20',
            'name' => 'sometimes|string|max:255',
            'last_name' => 'sometimes|string|max:255',
            'email' => 'sometimes|nullable|email',
            'phone_number' => 'sometimes|nullable|string',
            'address' => 'sometimes|nullable|string',
            'matters' => 'sometimes|nullable|string',
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
            $this->teacherService->retryImport($id);

            return response()->json(['success' => true]);
        } catch (Exception $e) {
            Log::error('Error al reintentar importación de profesor ID '.$id.': '.$e->getMessage());

            return response()->json(['success' => false, 'error' => ErrorTranslator::translate($e)], 422);
        }
    }

    public function destroy($id)
    {
        $failedImport = FailedImport::findOrFail($id);
        $failedImport->delete();

        return response()->json(['success' => true]);
    }

    public function destroyAll(Request $request)
    {
        $type = $request->input('type', 'all');

        $query = FailedImport::query();
        if ($type === 'teacher') {
            $query->where('import_type', 'teacher');
        } elseif ($type === 'student') {
            $query->where(function ($q) {
                $q->where('import_type', 'student')->orWhereNull('import_type');
            });
        }

        $count = $query->count();
        $query->delete();

        return response()->json(['success' => true, 'deleted' => $count]);
    }
}
