<?php

namespace App\Http\Controllers;

use App\Support\ErrorTranslator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

class SchoolLapseController extends Controller
{
    public function startNext(Request $request)
    {
        try {
            $exitCode = Artisan::call('lapse:start-next');
            $output = Artisan::output();

            if ($exitCode === 0) {
                return response()->json([
                    'success' => true,
                    'message' => 'Próximo periodo escolar iniciado correctamente.',
                    'output' => $output,
                ]);
            }

            Log::error('Error al iniciar el próximo periodo escolar', [
                'exit_code' => $exitCode,
                'output' => $output,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Ocurrió un error al iniciar el próximo periodo escolar.',
                'output' => $output,
            ], 500);
        } catch (\Exception $e) {
            Log::error('Excepción al iniciar el próximo periodo escolar: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => ErrorTranslator::translate($e),
            ], 500);
        }
    }
}
